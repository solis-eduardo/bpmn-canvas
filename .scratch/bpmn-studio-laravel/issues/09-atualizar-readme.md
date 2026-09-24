# 09: Atualizar README.md

**What to build:** O `README.md` da raiz do repositório passa a descrever a arquitetura atual (aplicação Laravel, contas de Usuário, Diagrama/Autor/Colaborador, Compartilhamento, Trava de Edição, Versionamento), em vez da descrição do stack legado (nginx + WebDAV + Basic Auth), que hoje induz quem abre o repositório ao erro.

**Blocked by:** None (recomenda-se fazer por último, para descrever o sistema já construído em vez de um plano ainda incompleto)

**Status:** ready-for-agent

- [ ] O README descreve a stack atual (Laravel + Postgres + Keycloak opcional), não mais nginx/WebDAV como mecanismo principal.
- [ ] O README usa o vocabulário do `CONTEXT.md` (Diagrama, Autor, Colaborador, Compartilhamento, Trava de Edição, Versão), sem termos do glossário antigo (ex.: "Owner", "Fluxo", "Token de compartilhamento").
- [ ] O README explica como subir o ambiente (`docker compose up`) e aponta que a integração com Keycloak é opcional/adiada, com contas locais como caminho padrão nesta fase.
- [ ] A menção ao stack legado (se mantida por contexto histórico) deixa claro que ele está em `legacy/` e não é mais a aplicação em uso.
- [ ] Instruções de uso (criar Diagrama, compartilhar, colaborar) refletem o comportamento real implementado até o momento da atualização deste README, não funcionalidades planejadas e ainda não construídas.
</content>
