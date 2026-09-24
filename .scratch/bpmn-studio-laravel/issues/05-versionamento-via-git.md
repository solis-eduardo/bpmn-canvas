# 05: Versionamento via Git

**What to build:** Cada save de um Diagrama gera uma Versão, guardada internamente via um repositório Git próprio do Diagrama (ver ADR-0004), mas exposta ao Usuário sem nenhum vocabulário de Git — só "Versão", "salvo em" e "restaurar". O histórico é listado agregado por dia; restaurar uma Versão antiga cria uma nova Versão no topo do histórico (estilo revert), sem apagar nada.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Cada save de um Diagrama gera uma nova Versão internamente (um commit no repositório Git próprio desse Diagrama), sem descartar saves intermediários.
- [ ] Um Usuário consegue ver o histórico de Versões de um Diagrama, agrupado por dia de calendário.
- [ ] Nenhuma tela ou resposta da aplicação expõe termos de Git (commit, branch, etc.) — só "Versão"/"salvo em"/"restaurar".
- [ ] Um Usuário consegue restaurar uma Versão antiga; isso torna o conteúdo do Diagrama igual ao daquela Versão.
- [ ] Restaurar uma Versão antiga cria uma nova Versão no topo do histórico (o histórico entre a versão restaurada e o momento do restore continua existindo e visível).
- [ ] Testes de Feature cobrem: múltiplos saves gerando múltiplas Versões, listagem agregada por dia, e restauração (incluindo verificar que o histórico anterior ao restore não é apagado) — tudo observado pelas rotas HTTP, sem inspecionar o repositório Git diretamente no teste.
</content>
