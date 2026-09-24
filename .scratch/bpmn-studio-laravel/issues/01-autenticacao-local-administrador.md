# 01: Autenticação local + Administrador

**What to build:** Um Usuário consegue entrar no sistema com uma conta local (sem depender de Keycloak, que fica adiado para um ticket independente). Um Administrador consegue criar contas de Usuário manualmente. Existe pelo menos um Administrador disponível desde o primeiro deploy, para poder criar as demais contas.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Existe um mecanismo de login local (email/senha, ou equivalente do ecossistema Laravel) funcionando ponta a ponta: Usuário não autenticado é barrado das rotas autenticadas, consegue logar e acessar depois.
- [ ] Um Administrador consegue criar uma conta de Usuário (nome, e-mail, senha inicial ou fluxo de definição de senha).
- [ ] Um Usuário sem privilégio de Administrador não consegue criar outras contas.
- [ ] O papel de Administrador é representado como uma flag no Usuário; existe pelo menos um Administrador semeado (seeder) desde o início, para não haver um problema de "ovo e galinha" na criação das demais contas.
- [ ] Um Administrador consegue conceder/revogar o papel de Administrador a outro Usuário.
- [ ] O modelo de dados do Usuário já reserva espaço para uma futura identidade externa (ex.: um identificador de Keycloak nullable), sem que nada dependa dele nesta fase — ver ticket 08.
</content>
