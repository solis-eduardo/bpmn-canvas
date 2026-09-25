# BPMN Studio

Self-hosted Laravel application for modeling and sharing BPMN 2.0 Diagrams, with real User accounts, per-Diagram authorship/collaboration, an Editing Lock, and automatic Versioning.

See `CONTEXT.md` for the full domain glossary (Diagram, Author, Collaborator, Share, Editing Lock, Version, User) and `docs/adr/` for the architectural decisions.

## Stack

- **Laravel 13 (PHP 8.4)** — `app`, served via PHP-FPM.
- **PostgreSQL 16** — `db`, metadata for Users, Diagrams, Collaborators, Shares.
- **nginx** — `web`, reverse proxy to PHP-FPM and static file server (CSS/JS/vendored bpmn-js).
- Each Diagram lives at `storage/app/diagrams/{slug}/diagram.bpmn`, with its own Git repository in that same directory for the Version history (never exposed to the User as "Git" — see `docs/adr/0004`).

## Bringing up the environment

```bash
cp .env.example .env
php artisan key:generate          # or set APP_KEY manually
# set DB_PASSWORD and, at minimum, ADMIN_EMAIL/ADMIN_PASSWORD in .env
docker compose up -d --build
```

On first boot, the `app` container runs the migrations and seeds an initial Administrator from `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME` (if not set, it uses a default email derived from `APP_URL` and generates a random password, shown once in the container log — `docker compose logs app`). That Administrator can then create the other accounts manually through the app.

Visit `http://localhost/login`.

## Authentication

Two login methods coexist — the User chooses which to use, and one doesn't replace the other:

- **Local** (default in this phase): email/password, account created manually by an Administrator.
- **Keycloak (SSO, optional)**: if `KEYCLOAK_BASE_URL`/`KEYCLOAK_REALM`/`KEYCLOAK_CLIENT_ID`/`KEYCLOAK_CLIENT_SECRET` are configured in `.env`, the Keycloak login button becomes available. Just-in-time provisioning on first access; a pre-existing local account with the same email is automatically linked to the Keycloak identity (never duplicated). A Keycloak role/claim (`KEYCLOAK_ADMIN_ROLE`, default `bpmn-admin`) can grant the Administrator role, but never revokes a manual grant.

## Usage

1. **Create a Diagram**: at `/diagrams`, give it a name — a Slug is derived automatically.
2. **Editor**: opens the bpmn-js Modeler. While it's open, you hold the Editing Lock for that Diagram — anyone else who tries to open the same Editor sees who's editing and can request the Lock (transferred automatically after 30s with no response) or create a Fork (independent copy) to proceed without waiting.
3. **Renaming** updates the Slug; the previous Slug keeps working (redirects to the current one), even after several renames.
4. **Collaborators**: as Author, invite another User directly (immediate access) or approve an access request; an Administrator can also grant access on any Diagram.
5. **Sharing**: generates a signed public link, expiring in days (default 7, max 60) with an option to allow download (disabled by default). Revocable at any time; a Diagram can have several active Shares at once. Whoever receives the link can view it without an account.
6. **Versions**: every save generates a Version, listed grouped by day; restoring an old Version creates a new Version on top of the history, without deleting anything.

## Main environment variables

See `.env.example` for the full list. Highlights:

| Variable | Purpose |
|---|---|
| `DB_*` | Postgres connection (`db` in docker-compose) |
| `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` | Administrator seeded on first deploy |
| `KEYCLOAK_*` | Optional SSO integration (leave blank to use local login only) |
| `BPMN_JS_VERSION` | Version of bpmn-js vendored in the build (no CDN at runtime) |

## Production

- Use HTTPS — put it behind a reverse proxy (the `web` service is already set up for this, see the external `nginx-network` network in `docker-compose.yml`).
- `storage/app/diagrams` is bind-mounted to `./diagrams` on the host — include it in your backups.
- Set `APP_ENV=production` and `APP_DEBUG=false` in your production `.env`.

## Structure

```
app/Http/Controllers/       # Diagram, Collaborators, Shares, Auth
app/Models/                 # Diagram, User, DiagramCollaborator, Share
app/Repositories/           # DiagramRepository, VersionRepository (Git-backed versioning, ADR-0004)
app/Actions/                # Editing Lock, Collaborators
app/Policies/                # DiagramPolicy
database/migrations/
routes/web.php
resources/views/
public/assets/              # editor.js/viewer, adapted from the original nginx+WebDAV stack
docker-compose.yml          # app (PHP-FPM) + web (nginx) + db (Postgres)
Dockerfile                  # multi-stage: vendored bpmn-js + composer + app + web
CONTEXT.md                  # domain glossary
docs/GLOSSARY.md            # PT-BR -> English naming thesaurus
docs/adr/                   # architectural decisions
.scratch/bpmn-studio-laravel/  # implementation spec and tickets (kept in Portuguese)
```

## bpmn-js license

bpmn-js is free, including for commercial use, but its license requires the
**bpmn.io** watermark shown in the corner of the diagram to remain visible.

## License

MIT — see `LICENSE`.

---

# BPMN Studio (Português)

Aplicação Laravel self-hosted para modelar e compartilhar Diagramas BPMN 2.0, com contas de Usuário reais, autoria/colaboração por Diagrama, Trava de Edição e Versionamento automático.

Veja `CONTEXT.md` para o glossário completo do domínio (Diagrama, Autor, Colaborador, Compartilhamento, Trava de Edição, Versão, Usuário) e `docs/adr/` para as decisões arquiteturais.

## Stack

- **Laravel 13 (PHP 8.4)** — `app`, servido via PHP-FPM.
- **PostgreSQL 16** — `db`, metadados de Usuários, Diagramas, Colaboradores, Compartilhamentos.
- **nginx** — `web`, reverse proxy para o PHP-FPM e servidor dos estáticos (CSS/JS/bpmn-js vendorizado).
- Cada Diagrama vive em `storage/app/diagrams/{slug}/diagram.bpmn`, com um repositório Git próprio nesse mesmo diretório para o histórico de Versões (nunca exposto ao Usuário como "Git" — ver `docs/adr/0004`).

## Subindo o ambiente

```bash
cp .env.example .env
php artisan key:generate          # ou defina APP_KEY manualmente
# defina DB_PASSWORD e, no mínimo, ADMIN_EMAIL/ADMIN_PASSWORD no .env
docker compose up -d --build
```

No primeiro boot, o container `app` roda as migrations e semeia um Administrador inicial a partir de `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME` (se não definidos, usa um e-mail default derivado de `APP_URL` e gera uma senha aleatória, exibida uma única vez no log do container — `docker compose logs app`). Esse Administrador consegue criar as demais contas manualmente pela aplicação.

Acesse `http://localhost/login`.

## Autenticação

Duas formas de login coexistem — o Usuário escolhe qual usar, uma não substitui a outra:

- **Local** (padrão desta fase): e-mail/senha, conta criada manualmente por um Administrador.
- **Keycloak (SSO, opcional)**: se `KEYCLOAK_BASE_URL`/`KEYCLOAK_REALM`/`KEYCLOAK_CLIENT_ID`/`KEYCLOAK_CLIENT_SECRET` estiverem configurados no `.env`, o botão de login via Keycloak fica disponível. Provisionamento just-in-time no primeiro acesso; uma conta local pré-existente com o mesmo e-mail é vinculada automaticamente à identidade Keycloak (nunca duplicada). Uma role/claim do Keycloak (`KEYCLOAK_ADMIN_ROLE`, default `bpmn-admin`) pode conceder o papel de Administrador, mas nunca revoga uma concessão manual.

## Uso

1. **Criar um Diagrama**: em `/diagrams`, dê um nome — um Slug é derivado automaticamente.
2. **Editor**: abre o bpmn-js Modeler. Enquanto aberto, você detém a Trava de Edição daquele Diagrama — outra pessoa que tentar abrir o mesmo Editor vê quem está editando e pode solicitar a Trava (transferida automaticamente após 30s sem resposta) ou criar um Fork (cópia independente) para seguir sem esperar.
3. **Renomear** atualiza o Slug; o Slug anterior continua funcionando (redireciona para o atual), mesmo após várias renomeações.
4. **Colaboradores**: como Autor, convide outro Usuário diretamente (acesso imediato) ou aprove uma solicitação de acesso; um Administrador também pode conceder acesso em qualquer Diagrama.
5. **Compartilhar**: gera um link público assinado, com expiração em dias (padrão 7, máximo 60) e uma opção de permitir download (desabilitada por padrão). Revogável a qualquer momento; um Diagrama pode ter vários links de Compartilhamento ativos simultaneamente. Quem recebe o link visualiza sem precisar de conta.
6. **Versões**: cada save gera uma Versão, listada agrupada por dia; restaurar uma Versão antiga cria uma nova Versão no topo do histórico, sem apagar nada.

## Variáveis de ambiente principais

Ver `.env.example` para a lista completa. Destaques:

| Variável | Função |
|---|---|
| `DB_*` | Conexão com o Postgres (`db` no docker-compose) |
| `ADMIN_NAME`/`ADMIN_EMAIL`/`ADMIN_PASSWORD` | Administrador semeado no primeiro deploy |
| `KEYCLOAK_*` | Integração SSO opcional (deixe em branco para usar só login local) |
| `BPMN_JS_VERSION` | Versão do bpmn-js vendorizada no build (sem CDN em runtime) |

## Produção

- Use HTTPS — coloque atrás de um reverse proxy (o serviço `web` já está preparado para isso, ver rede externa `nginx-network` no `docker-compose.yml`).
- `storage/app/diagrams` é bind-mounted em `./diagrams` no host — inclua no seu backup.
- Ajuste `APP_ENV=production` e `APP_DEBUG=false` no `.env` de produção.

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
Dockerfile                  # multi-stage: bpmn-js vendorizado + composer + app + web
CONTEXT.md                  # glossário do domínio
docs/GLOSSARY.md            # tesauro de nomenclatura PT-BR -> inglês
docs/adr/                   # decisões arquiteturais
.scratch/bpmn-studio-laravel/  # spec e tickets de implementação (mantido em português)
```

## Licença do bpmn-js

O bpmn-js é gratuito, inclusive para uso comercial, mas a licença exige que a marca
**bpmn.io** exibida no canto do diagrama permaneça visível.

## Licença

MIT — ver `LICENSE`.
