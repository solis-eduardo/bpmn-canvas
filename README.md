# BPMN Studio

Aplicação Laravel self-hosted para modelar e compartilhar Diagramas BPMN 2.0, com contas de Usuário reais, autoria/colaboração por Diagrama, Trava de Edição e Versionamento automático.

Ver `CONTEXT.md` para o glossário completo do domínio (Diagrama, Autor, Colaborador, Compartilhamento, Trava de Edição, Versão, Usuário) e `docs/adr/` para as decisões arquiteturais.

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

1. **Criar um Diagrama**: em `/diagramas`, dê um nome — um Slug é derivado automaticamente.
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
app/Models/                 # Diagrama, User, DiagramaColaborador, Compartilhamento
app/Services/VersaoRepository.php  # Versionamento via Git interno (ADR-0004)
app/Actions/                # Trava de Edição, Colaboradores
database/migrations/
routes/web.php
resources/views/
public/assets/              # editor.js/viewer, adaptados do stack legado
docker-compose.yml          # app (PHP-FPM) + web (nginx) + db (Postgres)
Dockerfile                  # multi-stage: bpmn-js vendorizado + composer + app + web
CONTEXT.md                  # glossário do domínio
docs/adr/                   # decisões arquiteturais
.scratch/bpmn-studio-laravel/  # spec e tickets de implementação
legacy/                     # stack antigo (nginx + WebDAV + Basic Auth) — histórico, fora de uso
```

## Stack legado

`legacy/` preserva o stack original (nginx + WebDAV + Basic Auth, um único usuário compartilhado, sem histórico nem colaboração) que motivou esta reconstrução. Não é mais executado nem mantido — ver `docs/adr/0001` a `0004` para o porquê de cada mudança.

## Licença do bpmn-js

O bpmn-js é gratuito, inclusive para uso comercial, mas a licença exige que a marca
**bpmn.io** exibida no canto do diagrama permaneça visível.
