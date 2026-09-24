# 08: Integração SSO com Keycloak (opcional)

**What to build:** Um Usuário consegue entrar no sistema com sua conta do Keycloak da organização, além de (não em vez de) continuar podendo usar contas locais criadas por um Administrador (ticket 01). No primeiro login via Keycloak, uma conta é provisionada automaticamente (just-in-time). O papel de Administrador passa a poder vir também de uma role/claim do token do Keycloak, além da flag manual já existente. Este ticket fica parado até o client deste projeto ser criado no Keycloak da organização, e nenhum outro ticket depende dele.

**Blocked by:** 01

**Status:** done

- [x] Um Usuário consegue entrar via Keycloak (OpenID Connect); a autenticação local do ticket 01 continua funcionando normalmente para quem já tem conta local.
- [x] No primeiro login via Keycloak de um identificador ainda não conhecido, uma nova conta de Usuário é criada automaticamente (provisionamento just-in-time), sem exigir criação manual prévia por um Administrador.
- [x] Em logins seguintes, o mesmo identificador do Keycloak sempre resolve para a mesma conta já criada.
- [x] O papel de Administrador é verdadeiro se a claim/role correspondente estiver presente no token do Keycloak, **ou** se a flag manual (ticket 01) já estiver marcada — as duas origens coexistem.
- [x] Testes de Feature cobrem login via Keycloak simulado (sem depender de um Keycloak real em execução), provisionamento JIT, reuso de conta em login subsequente, e papel de Administrador via claim.

## Comments

Implementado via TDD (7 novos testes de Feature em `tests/Feature/Auth/KeycloakLoginTest.php`, seam HTTP: rota `/login/keycloak/callback` exercitada de ponta a ponta, mockando apenas `Laravel\Socialite\Contracts\Provider` — retornado por `Socialite::shouldReceive('driver')->with('keycloak')` — com um `Laravel\Socialite\Two\User::fake()` cujo `token` é um JWT fabricado (header/payload base64url, sem assinatura válida) contendo `realm_access.roles`; nada dentro do próprio app é mockado).

Pacotes: `laravel/socialite` (^5.31) e `socialiteproviders/keycloak` (^5.3). As chaves de config exigidas pela v5.3 do provider (`SocialiteProviders\Keycloak\Provider::additionalConfigKeys()`) são `base_url` e `realms` (plural), além das chaves padrão do Socialite `client_id`, `client_secret`, `redirect` — todas em `config/services.php` sob a chave `keycloak`, lidas de `KEYCLOAK_BASE_URL`, `KEYCLOAK_REALM`, `KEYCLOAK_CLIENT_ID`, `KEYCLOAK_CLIENT_SECRET`, `KEYCLOAK_REDIRECT_URI`. O listener do pacote (`SocialiteProviders\Keycloak\KeycloakExtendSocialite::handle` no evento `SocialiteProviders\Manager\SocialiteWasCalled`) foi registrado em `AppServiceProvider::boot()` via `Event::listen(...)`, já que este projeto (Laravel 13 skeleton) não tem `EventServiceProvider` — não há outro lugar convencional para isso nesta versão.

Nova config `bpmn.keycloak_admin_role` (env `KEYCLOAK_ADMIN_ROLE`, default `bpmn-admin`) nomeia a role do Keycloak que concede `is_admin`. A claim é lida decodificando manualmente o `access_token` (Socialite `$user->token`) como JWT, sem verificar assinatura — aceitável porque o token chega via troca server-to-server confiável (Authorization Code direto com o Keycloak), e comparada contra `realm_access.roles`. `is_admin` só é ligado (nunca desligado) por essa via: um `is_admin=true` concedido manualmente por outro Administrador nunca é revogado por ausência da claim.

**Regra definida para colisão de e-mail entre conta local e conta Keycloak:** login via Keycloak resolve a conta primeiro por `keycloak_id`. Se não encontrar, procura uma conta local existente (`keycloak_id IS NULL`) com o mesmo e-mail retornado pelo Keycloak e **vincula** essa conta à identidade Keycloak (grava o `keycloak_id` nela), em vez de criar uma conta nova duplicada — o que também violaria a constraint `unique` de `email`. Presume-se que o mesmo e-mail identifica a mesma pessoa. Só quando nenhuma conta (nem por `keycloak_id`, nem por `email`) é encontrada é que uma conta nova é provisionada (JIT), com senha aleatória (`Hash::make(Str::random(40))`) já que o login será sempre via Keycloak dali em diante.

Nota de infraestrutura (fora do escopo funcional do ticket, mas necessária para rodar a suíte neste worktree): o worktree git não trazia `.env` nem os diretórios `storage/framework/{cache/data,sessions,testing,views}` (git não versiona diretórios vazios e não havia `.gitkeep`); foram criados localmente (`.env` a partir de `.env.example` + `php artisan key:generate`, diretórios criados na hora) só para viabilizar `php artisan test` — nada disso foi commitado, exceto os `.gitkeep` (ver commit), para não deixar o mesmo obstáculo para o próximo agente/desenvolvedor.
</content>
