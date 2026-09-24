# 06: Colaboração

**What to build:** Um Diagrama passa a ter um Autor e, opcionalmente, Colaboradores com permissão de editar. O Autor convida diretamente (acesso imediato) ou aprova solicitações de acesso; um Administrador também pode conceder acesso independentemente do Autor. Editar um Diagrama (abrir o Editor, salvar) passa a exigir ser o Autor ou um Colaborador aprovado.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Cada Diagrama tem um Autor (quem o criou).
- [ ] O Autor consegue convidar diretamente outro Usuário como Colaborador; o acesso é concedido imediatamente, sem etapa de aceite.
- [ ] Um Usuário consegue solicitar acesso de Colaborador a um Diagrama que não é seu.
- [ ] O Autor consegue ver e aprovar (ou recusar) solicitações pendentes de acesso ao seu Diagrama.
- [ ] Um Administrador consegue conceder acesso de Colaborador em qualquer Diagrama, independentemente do Autor.
- [ ] O Autor consegue remover um Colaborador do seu Diagrama a qualquer momento.
- [ ] Abrir o Editor e salvar um Diagrama só é permitido para o Autor ou para um Colaborador com acesso aprovado; qualquer outro Usuário autenticado é barrado dessas ações (a listagem geral do Diagrama pode continuar visível a todos, como no ticket 02 — refinar visibilidade de listagem fica fora deste ticket se não houver necessidade explícita).
- [ ] Testes de Feature cobrem: convite direto, solicitação + aprovação, recusa, concessão por Administrador, remoção, e bloqueio de edição para quem não é Autor nem Colaborador aprovado.
</content>
