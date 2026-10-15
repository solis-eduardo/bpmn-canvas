# BPMN Canvas

Self-hosted Laravel application for modeling and sharing BPMN 2.0 Diagrams, with real User accounts, per-Diagram authorship/collaboration, an Editing Lock, and automatic Versioning.

See `CONTEXT.md` for the full domain glossary (Diagram, Author, Collaborator, Share, Editing Lock, Version, User) and `docs/adr/` for the architectural decisions.

## Stack

- **Laravel 13 (PHP 8.4)** — `app`, served via PHP-FPM.
- **PostgreSQL 16** — `db`, metadata for Users, Diagrams, Collaborators, Shares.
- **nginx** — `web`, reverse proxy to PHP-FPM and static file server (CSS/JS/vendored bpmn-js).
- Each Diagram lives at `storage/app/diagrams/{slug}/diagram.bpmn`, with its own Git repository in that same directory for the Version history (never exposed to the User as "Git" — see `docs/adr/0004`).

## Bringing up the environment

Requires only Docker and Docker Compose — no local PHP, Composer or Node install.

```bash
cp .env.example .env
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
# set DB_PASSWORD and, at minimum, ADMIN_EMAIL/ADMIN_PASSWORD in .env
docker compose up -d --build
```

On first boot, the `app` container runs the migrations and seeds an initial Administrator from `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME` (if not set, it uses a default email derived from `APP_URL` and generates a random password, shown once in the container log — `docker compose logs app`). That Administrator can then create the other accounts manually through the app.

Visit `http://localhost/login` (or `http://localhost:$APP_PORT/login` if you changed `APP_PORT` in `.env`, e.g. because port 80 is already taken).

> No `openssl` available (e.g. Windows without WSL)? Any Laravel `APP_KEY` works — generate one with `docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"` and paste it into `.env` instead.

## Authentication

Two login methods coexist — the User chooses which to use, and one doesn't replace the other:

- **Local** (default in this phase): email/password, account created manually by an Administrator.
- **Keycloak (SSO, optional)**: if `KEYCLOAK_BASE_URL`/`KEYCLOAK_REALM`/`KEYCLOAK_CLIENT_ID`/`KEYCLOAK_CLIENT_SECRET` are configured in `.env`, the Keycloak login button becomes available. Just-in-time provisioning on first access; a pre-existing local account with the same email is automatically linked to the Keycloak identity (never duplicated). A Keycloak role/claim (`KEYCLOAK_ADMIN_ROLE`, default `bpmn-admin`) can grant the Administrator role, but never revokes a manual grant.

## Email notifications

Users can opt in to email copies of their notifications from **Email preferences** in the navigation. Email is off by default. The `queue` Compose service delivers queued mail; set `MAIL_MAILER` and the corresponding mailer variables in `.env` to configure delivery. The development default, `MAIL_MAILER=log`, writes outgoing messages to the application log instead of sending them.

## Usage

1. **Create a Diagram**: at `/diagrams`, give it a name — a Slug is derived automatically.
2. **Editor**: opens the bpmn-js Modeler and acquires that Diagram's Editing Lock. You can hold Locks on multiple Diagrams. Leaving or closing the Editor and inactivity do not release a Lock; explicitly release it when you're done. Anyone else who tries to open the same Editor sees who's holding the Lock and can request it (the holder is notified, but requests never transfer the Lock automatically) or create a Fork (independent copy). An Administrator can explicitly force-release it.
3. **Renaming** updates the Slug; the previous Slug keeps working (redirects to the current one), even after several renames.
4. **Collaborators**: as Author, invite another User directly (immediate access) or approve an access request; an Administrator can also grant access on any Diagram.
5. **Sharing**: generates a public link with an unguessable token, expiring in days (default 7, max 60) with an option to allow download (disabled by default). Revocable at any time; a Diagram can have several active Shares at once. Whoever receives the link can view it without an account. When download is allowed, the link also serves PNG, SVG and PDF files rendered on the server, besides the `.bpmn`; signed-in Users can export the same formats from the Editor. The PDF carries a title block (title, Version, Diagram Owner, date). Rendering runs headless bpmn-js in Chromium (`docker/renderer`; the Docker image includes Node and Chromium, a host install sets `RENDERER_CHROMIUM_PATH`).
6. **Versions**: every save generates a Version, listed grouped by day; restoring an old Version creates a new Version on top of the history, without deleting anything. Anyone who can edit the Diagram can also compare an old Version against the current content directly on the canvas — added, removed and changed elements are highlighted.

## Main environment variables

See `.env.example` for the full list. Highlights:

| Variable | Purpose |
|---|---|
| `DB_*` | Postgres connection (`db` in docker-compose) |
| `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` | Administrator seeded on first deploy |
| `KEYCLOAK_*` | Optional SSO integration (leave blank to use local login only) |
| `APP_PORT` | Host port `web` publishes (default `80`) |
| `MAIL_MAILER` | Mail transport (defaults to `log`; configure `smtp` and `MAIL_HOST`/credentials to send mail) |
| `QUEUE_CONNECTION` | Queue backend used for queued mail (defaults to `database`) |

## Production

- Use HTTPS. Bring the stack up with the production overlay: `docker network create nginx-network` (once, if it doesn't exist) then `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`. It layers `.env.prod` (non-secret production settings) over `.env`, attaches `web` to the shared reverse proxy's `nginx-network` and unpublishes its host port. Without a shared proxy, publish `web` yourself (drop the `ports: !reset []` line in the overlay).
- `storage/app/diagrams` is bind-mounted to `./diagrams` on the host — include it in your backups.
- `.env.prod` (loaded by the overlay) sets `APP_ENV=production`, `APP_DEBUG=false` and stderr logging; with `APP_ENV=production` the `app` entrypoint also runs `php artisan optimize` on boot. Keep secrets in `.env`.
- Versions of the vendored browser libraries (bpmn-js, bpmn-js-differ) are pinned in `docker/vendor/package.json` / `package-lock.json`.
- Configure a production mail transport using `MAIL_MAILER` and its `MAIL_*` settings; the default `log` mailer does not deliver messages.

## Backup and Restore

The `scheduler` compose service runs `backup:run` on a schedule (see `docs/adr/0008`). Each run produces one artifact — a `.tar.gz` containing a copy of the Diagrams directory (with each Diagram's Version history) and a `pg_dump --format=custom` database dump — on the `backups` disk, and prunes artifacts past the retention window.

| Variable | Purpose |
|---|---|
| `BACKUP_DESTINATION` | Where artifacts are written (default `storage/app/backup`, normally a host-mounted volume — see `./backups` in docker-compose.yml) |
| `BACKUP_RETENTION_DAYS` | How many days an artifact is kept before the next `backup:run` prunes it (default `14`) |

To restore an artifact onto a running stack:

```bash
# List available artifacts
docker compose exec app ls storage/app/backup   # or your BACKUP_DESTINATION

# Restore one — prompts for confirmation before overwriting anything
docker compose exec app php artisan backup:restore backup-2026-01-01_000000-abcd1234.tar.gz

# Non-interactive (scripted) use skips the prompt
docker compose exec app php artisan backup:restore backup-2026-01-01_000000-abcd1234.tar.gz --force
```

`backup:restore` validates the artifact's `manifest.json` before touching anything, then **overwrites** the current database and Diagrams directory with the artifact's contents — nothing is merged. It is an operator command, run manually; unlike `backup:run` it is not on the scheduler. After a restore, Diagrams open normally, their Version history lists and restores as before, and existing Share links keep working — the round trip has been exercised end to end against this stack.

## Standalone build

A single HTML file that runs the Editor entirely in the browser, with no server, login or database (ADR-0019). Diagrams and their Versions are stored in the browser's IndexedDB, so they live in the browser profile and are lost if the site data is cleared: use **Export all (.zip)** for a backup.

```bash
npm install
npm run build:standalone    # needs PHP: it first runs `php artisan lang:export`
```

The result is `standalone/dist/bpmn-canvas.html`. Open it from disk (`file://`) or put it on any static host. It makes no external requests. It can create, edit and save Diagrams, keeps a Version per save (compare, restore, rename), exports PNG, SVG and BPMN, imports a `.bpmn` (including one exported by the server build), and exports every Diagram as a `.zip`. In Chromium, **Save to file…** writes to a `.bpmn` file of your choice on every save; elsewhere it downloads the file. Title and Tags editing, Locks, Collaborators, Workspaces and Shares are not part of it.

## Browser demo

A hosted demo runs the real app in the visitor's browser: <https://solis-eduardo.github.io/bpmn-canvas/>. PHP runs as WebAssembly (`@php-wasm/web`) behind a Service Worker, so routes, controllers, Blade views, policies and validation are the ones on `main`. `.github/workflows/demo.yml` rebuilds it (`demo/build.sh`) and publishes it to GitHub Pages on every push to `main`, after a headless-browser smoke test (`demo/smoke.mjs`) has passed.

What differs from a real deployment:

- Data lives only in the visitor's browser. Clearing site data, or **Reset demo data** in the top bar, starts over from the seeded demo.
- No email (`MAIL_MAILER=log`, nothing is sent), no Keycloak/SSO, no backups, and no scheduler: scheduled tasks do not run.
- Versions are stored in the database (`VERSION_STORE=database`), not in Git.
- The demo accounts (`admin@demo.test`, `author@demo.test`, `collaborator@demo.test`) share a public password, and the published bundle contains the application source and the demo database.

To build and test it locally you need PHP 8.4 (with `pdo_sqlite`), Composer, Node 22 and Chrome:

```bash
demo/build.sh                 # writes demo/dist (builds from HEAD)
cd demo && node smoke.mjs dist
```

## Structure

```
app/Http/Controllers/       # Diagram, Collaborators, Shares, Auth
app/Models/                 # Diagram, User, DiagramCollaborator, Share
app/Repositories/           # DiagramRepository, VersionRepository (port: GitVersionRepository by default, DatabaseVersionRepository; ADR-0004, ADR-0018)
app/Actions/                # Editing Lock, Collaborators
app/Policies/                # DiagramPolicy
database/migrations/
routes/web.php
resources/views/
public/assets/              # editor.js/viewer, adapted from the original nginx+WebDAV stack
docker-compose.yml          # app (PHP-FPM) + web (nginx) + db (Postgres)
docker-compose.prod.yml     # production overlay: .env.prod + shared reverse-proxy network
.env.prod                   # non-secret production overrides (layered over .env)
Dockerfile                  # multi-stage: vendored bpmn-js + composer + app + web
CONTEXT.md                  # domain glossary
docs/adr/                   # architectural decisions
```

## bpmn-js license

bpmn-js is free, including for commercial use, but its license requires the
**bpmn.io** watermark shown in the corner of the diagram to remain visible.

## License

MIT — see `LICENSE`.

---

# BPMN Canvas (Português)

Aplicação Laravel self-hosted para modelar e compartilhar Diagramas BPMN 2.0, com contas de Usuário reais, autoria/colaboração por Diagrama, Trava de Edição e Versionamento automático.

Veja `CONTEXT.md` para o glossário completo do domínio (Diagrama, Autor, Colaborador, Compartilhamento, Trava de Edição, Versão, Usuário) e `docs/adr/` para as decisões arquiteturais.

## Stack

- **Laravel 13 (PHP 8.4)** — `app`, servido via PHP-FPM.
- **PostgreSQL 16** — `db`, metadados de Usuários, Diagramas, Colaboradores, Compartilhamentos.
- **nginx** — `web`, reverse proxy para o PHP-FPM e servidor dos estáticos (CSS/JS/bpmn-js vendorizado).
- Cada Diagrama vive em `storage/app/diagrams/{slug}/diagram.bpmn`, com um repositório Git próprio nesse mesmo diretório para o histórico de Versões (nunca exposto ao Usuário como "Git" — ver `docs/adr/0004`).

## Subindo o ambiente

Só precisa de Docker e Docker Compose — sem PHP, Composer ou Node instalados localmente.

```bash
cp .env.example .env
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
# defina DB_PASSWORD e, no mínimo, ADMIN_EMAIL/ADMIN_PASSWORD no .env
docker compose up -d --build
```

No primeiro boot, o container `app` roda as migrations e semeia um Administrador inicial a partir de `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME` (se não definidos, usa um e-mail default derivado de `APP_URL` e gera uma senha aleatória, exibida uma única vez no log do container — `docker compose logs app`). Esse Administrador consegue criar as demais contas manualmente pela aplicação.

Acesse `http://localhost/login` (ou `http://localhost:$APP_PORT/login` se você mudou `APP_PORT` no `.env`, por exemplo porque a porta 80 já está em uso).

> Sem `openssl` disponível (ex.: Windows sem WSL)? Qualquer `APP_KEY` válido do Laravel funciona — gere um com `docker run --rm php:8.4-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"` e cole no `.env`.

## Autenticação

Duas formas de login coexistem — o Usuário escolhe qual usar, uma não substitui a outra:

- **Local** (padrão desta fase): e-mail/senha, conta criada manualmente por um Administrador.
- **Keycloak (SSO, opcional)**: se `KEYCLOAK_BASE_URL`/`KEYCLOAK_REALM`/`KEYCLOAK_CLIENT_ID`/`KEYCLOAK_CLIENT_SECRET` estiverem configurados no `.env`, o botão de login via Keycloak fica disponível. Provisionamento just-in-time no primeiro acesso; uma conta local pré-existente com o mesmo e-mail é vinculada automaticamente à identidade Keycloak (nunca duplicada). Uma role/claim do Keycloak (`KEYCLOAK_ADMIN_ROLE`, default `bpmn-admin`) pode conceder o papel de Administrador, mas nunca revoga uma concessão manual.

## Notificações por e-mail

Usuários podem optar por receber cópias das notificações por e-mail em **Preferências de e-mail** na navegação. O envio fica desativado por padrão. O serviço Compose `queue` processa os e-mails enfileirados; configure `MAIL_MAILER` e as variáveis correspondentes no `.env`. O padrão de desenvolvimento, `MAIL_MAILER=log`, grava as mensagens enviadas no log da aplicação em vez de enviá-las.

## Uso

1. **Criar um Diagrama**: em `/diagrams`, dê um nome — um Slug é derivado automaticamente.
2. **Editor**: abre o bpmn-js Modeler e adquire a Trava de Edição daquele Diagrama. Você pode manter Travas em vários Diagramas. Sair ou fechar o Editor e ficar inativo não libera a Trava; use **Liberar Trava** quando terminar. Outra pessoa que tentar abrir o mesmo Editor vê quem detém a Trava e pode solicitar (o detentor é avisado, mas a solicitação nunca transfere a Trava automaticamente) ou criar um Fork (cópia independente). Um Administrador pode forçar explicitamente a liberação.
3. **Renomear** atualiza o Slug; o Slug anterior continua funcionando (redireciona para o atual), mesmo após várias renomeações.
4. **Colaboradores**: como Autor, convide outro Usuário diretamente (acesso imediato) ou aprove uma solicitação de acesso; um Administrador também pode conceder acesso em qualquer Diagrama.
5. **Compartilhar**: gera um link público assinado, com expiração em dias (padrão 7, máximo 60) e uma opção de permitir download (desabilitada por padrão). Revogável a qualquer momento; um Diagrama pode ter vários links de Compartilhamento ativos simultaneamente. Quem recebe o link visualiza sem precisar de conta. Com download permitido, o link também entrega PNG, SVG e PDF renderizados no servidor, além do `.bpmn`; Usuários autenticados exportam os mesmos formatos pelo Editor. O PDF leva um carimbo de título (título, Versão, Dono do Diagrama, data). A renderização roda o bpmn-js headless no Chromium (`docker/renderer`; a imagem Docker inclui Node e Chromium, numa instalação direta defina `RENDERER_CHROMIUM_PATH`).
6. **Versões**: cada save gera uma Versão, listada agrupada por dia; restaurar uma Versão antiga cria uma nova Versão no topo do histórico, sem apagar nada. Quem pode editar o Diagrama também pode comparar uma Versão antiga com o conteúdo atual diretamente no canvas — elementos adicionados, removidos e alterados são destacados.

## Variáveis de ambiente principais

Ver `.env.example` para a lista completa. Destaques:

| Variável | Função |
|---|---|
| `DB_*` | Conexão com o Postgres (`db` no docker-compose) |
| `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` | Administrador semeado no primeiro deploy |
| `KEYCLOAK_*` | Integração SSO opcional (deixe em branco para usar só login local) |
| `APP_PORT` | Porta do host publicada pelo `web` (default `80`) |
| `MAIL_MAILER` | Transporte de e-mail (padrão `log`; configure `smtp` e `MAIL_HOST`/credenciais para enviar e-mails) |
| `QUEUE_CONNECTION` | Backend da fila usada para os e-mails (padrão `database`) |

## Produção

- Use HTTPS. Suba a stack com o overlay de produção: `docker network create nginx-network` (uma vez, se ainda não existir) e depois `docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build`. Ele aplica o `.env.prod` (configurações de produção sem segredos) por cima do `.env`, liga o `web` à rede `nginx-network` do reverse proxy compartilhado e deixa de publicar a porta dele no host. Sem proxy compartilhado, publique o `web` você mesmo (remova a linha `ports: !reset []` do overlay).
- `storage/app/diagrams` é bind-mounted em `./diagrams` no host — inclua no seu backup.
- O `.env.prod` (carregado pelo overlay) define `APP_ENV=production`, `APP_DEBUG=false` e logs em stderr; com `APP_ENV=production` o entrypoint do `app` também roda `php artisan optimize` no boot. Mantenha os segredos no `.env`.
- As versões das bibliotecas de navegador vendorizadas (bpmn-js, bpmn-js-differ) ficam fixadas em `docker/vendor/package.json` / `package-lock.json`.
- Configure um transporte de e-mail de produção por `MAIL_MAILER` e as configurações `MAIL_*` correspondentes; o padrão `log` não entrega mensagens.

## Backup e Restauração

O serviço `scheduler` do compose roda `backup:run` periodicamente (veja `docs/adr/0008`). Cada execução produz um único artefato — um `.tar.gz` contendo uma cópia do diretório de Diagramas (com o histórico de Versões de cada Diagrama) e um dump do banco via `pg_dump --format=custom` — no disco `backups`, e remove artefatos além da janela de retenção.

| Variável | Finalidade |
|---|---|
| `BACKUP_DESTINATION` | Onde os artefatos são gravados (padrão `storage/app/backup`, normalmente um volume montado do host — veja `./backups` no docker-compose.yml) |
| `BACKUP_RETENTION_DAYS` | Quantos dias um artefato é mantido antes de ser removido pela próxima `backup:run` (padrão `14`) |

Para restaurar um artefato em uma stack em execução:

```bash
# Lista os artefatos disponíveis
docker compose exec app ls storage/app/backup   # ou seu BACKUP_DESTINATION

# Restaura um — pede confirmação antes de sobrescrever qualquer coisa
docker compose exec app php artisan backup:restore backup-2026-01-01_000000-abcd1234.tar.gz

# Uso não-interativo (script) pula a confirmação
docker compose exec app php artisan backup:restore backup-2026-01-01_000000-abcd1234.tar.gz --force
```

`backup:restore` valida o `manifest.json` do artefato antes de tocar em qualquer coisa, e então **sobrescreve** o banco de dados e o diretório de Diagramas atuais com o conteúdo do artefato — nada é mesclado. É um comando de operador, executado manualmente; diferente de `backup:run`, não está no scheduler. Após uma restauração, os Diagramas abrem normalmente, o histórico de Versões lista e restaura como antes, e links de Compartilhamento existentes continuam funcionando — o ciclo completo já foi executado de ponta a ponta nesta stack.

## Build standalone

Um único arquivo HTML que roda o Editor inteiramente no navegador, sem servidor, login ou banco de dados (ADR-0019). Os Diagramas e suas Versões ficam no IndexedDB do navegador, ou seja, no perfil do navegador, e se perdem se os dados do site forem limpos: use **Exportar tudo (.zip)** para fazer backup.

```bash
npm install
npm run build:standalone    # precisa do PHP: antes roda `php artisan lang:export`
```

O resultado é `standalone/dist/bpmn-canvas.html`. Abra-o do disco (`file://`) ou publique em qualquer hospedagem estática. Ele não faz requisições externas. Cria, edita e salva Diagramas, guarda uma Versão por salvamento (comparar, restaurar, renomear), exporta PNG, SVG e BPMN, importa um `.bpmn` (inclusive um exportado pela build do servidor) e exporta todos os Diagramas em um `.zip`. No Chromium, **Salvar em arquivo…** grava a cada salvamento em um `.bpmn` escolhido; nos demais navegadores baixa o arquivo. Edição de título e Tags, Locks, Colaboradores, Workspaces e Shares ficam de fora.

## Estrutura

```
app/Http/Controllers/       # Diagrama, Colaboradores, Compartilhamentos, Auth
app/Models/                 # Diagram, User, DiagramCollaborator, Share
app/Repositories/           # DiagramRepository, VersionRepository (versionamento via Git, ADR-0004)
app/Actions/                # Trava de Edição, Colaboradores
app/Policies/                # DiagramPolicy
database/migrations/
routes/web.php
resources/views/
public/assets/              # editor.js/viewer, adaptados do stack original nginx+WebDAV
docker-compose.yml          # app (PHP-FPM) + web (nginx) + db (Postgres)
docker-compose.prod.yml     # overlay de produção: .env.prod + rede do reverse proxy compartilhado
.env.prod                   # overrides de produção sem segredos (sobre o .env)
Dockerfile                  # multi-stage: bpmn-js vendorizado + composer + app + web
CONTEXT.md                  # glossário do domínio
docs/adr/                   # decisões arquiteturais
```

## Licença do bpmn-js

O bpmn-js é gratuito, inclusive para uso comercial, mas a licença exige que a marca
**bpmn.io** exibida no canto do diagrama permaneça visível.

## Licença

MIT — ver `LICENSE`.
