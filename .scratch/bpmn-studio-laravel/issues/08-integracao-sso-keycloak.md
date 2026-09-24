# 08: Integração SSO com Keycloak (opcional)

**What to build:** Um Usuário consegue entrar no sistema com sua conta do Keycloak da organização, além de (não em vez de) continuar podendo usar contas locais criadas por um Administrador (ticket 01). No primeiro login via Keycloak, uma conta é provisionada automaticamente (just-in-time). O papel de Administrador passa a poder vir também de uma role/claim do token do Keycloak, além da flag manual já existente. Este ticket fica parado até o client deste projeto ser criado no Keycloak da organização, e nenhum outro ticket depende dele.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Um Usuário consegue entrar via Keycloak (OpenID Connect); a autenticação local do ticket 01 continua funcionando normalmente para quem já tem conta local.
- [ ] No primeiro login via Keycloak de um identificador ainda não conhecido, uma nova conta de Usuário é criada automaticamente (provisionamento just-in-time), sem exigir criação manual prévia por um Administrador.
- [ ] Em logins seguintes, o mesmo identificador do Keycloak sempre resolve para a mesma conta já criada.
- [ ] O papel de Administrador é verdadeiro se a claim/role correspondente estiver presente no token do Keycloak, **ou** se a flag manual (ticket 01) já estiver marcada — as duas origens coexistem.
- [ ] Testes de Feature cobrem login via Keycloak simulado (sem depender de um Keycloak real em execução), provisionamento JIT, reuso de conta em login subsequente, e papel de Administrador via claim.
</content>
