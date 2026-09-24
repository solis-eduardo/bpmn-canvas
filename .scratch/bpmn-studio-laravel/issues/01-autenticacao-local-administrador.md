# 01: Autenticação local + Administrador

**What to build:** Um Usuário consegue entrar no sistema com uma conta local (sem depender de Keycloak, que fica adiado para um ticket independente). Um Administrador consegue criar contas de Usuário manualmente. Existe pelo menos um Administrador disponível desde o primeiro deploy, para poder criar as demais contas.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] Existe um mecanismo de login local (email/senha, ou equivalente do ecossistema Laravel) funcionando ponta a ponta: Usuário não autenticado é barrado das rotas autenticadas, consegue logar e acessar depois.
- [x] Um Administrador consegue criar uma conta de Usuário (nome, e-mail, senha inicial ou fluxo de definição de senha).
- [x] Um Usuário sem privilégio de Administrador não consegue criar outras contas.
- [x] O papel de Administrador é representado como uma flag no Usuário; existe pelo menos um Administrador semeado (seeder) desde o início, para não haver um problema de "ovo e galinha" na criação das demais contas.
- [x] Um Administrador consegue conceder/revogar o papel de Administrador a outro Usuário.
- [x] O modelo de dados do Usuário já reserva espaço para uma futura identidade externa (ex.: um identificador de Keycloak nullable), sem que nada dependa dele nesta fase — ver ticket 08.

## Comments

Implementado via TDD (14 testes de Feature, seam HTTP + banco): `LoginController` (login/logout por sessão), Gate `manage-users` + `Admin\UserController` (criar usuário, conceder/revogar `is_admin`), migration `is_admin`/`keycloak_id` nullable em `users`, `AdminUserSeeder` (idempotente, via `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME`, rodado automaticamente no boot do container `app`). Verificado ponta a ponta via HTTP contra a stack Docker (login real, cookie de sessão, `/home` autenticado).

Rodado `mattpocock-skills:code-review` (Standards + Spec) sobre este commit. Achado crítico corrigido: o seeder só criava o Administrador se `ADMIN_EMAIL` estivesse configurado — em um deploy "out of the box" (`.env.example` traz `ADMIN_EMAIL` vazio), nenhum Administrador era criado, quebrando a garantia "desde o início" que este critério exige. Corrigido para sempre criar um Administrador (e-mail default `admin@<host>` derivado de `APP_URL`, senha aleatória logada no console se `ADMIN_PASSWORD` não for definida). Também corrigidos 2 achados de Standards: `POST /login` sem rate limiting (`throttle:5,1` adicionado) e rotas `/admin/users` sem nome (`admin.users.store`/`admin.users.update`). 3 novos testes cobrindo esses casos — suíte final: 17 testes.
</content>
