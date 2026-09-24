# 03: Trava de Edição básica + Fork

**What to build:** Abrir o Editor de um Diagrama adquire uma Trava de Edição que impede outra pessoa de abrir o mesmo Editor ao mesmo tempo. Quem esbarra na Trava vê quem a detém e pode criar um Fork (cópia independente do Diagrama) em vez de esperar. A Trava é liberada ao sair do Editor ou por inatividade. (A solicitação/transferência/timeout de 30s e a força-liberação por Administrador ficam para o ticket 04.)

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Abrir o Editor de um Diagrama sem Trava ativa adquire a Trava para o Usuário atual.
- [ ] A listagem de Diagramas indica quais estão com Trava ativa no momento.
- [ ] Um Usuário que tenta abrir o Editor de um Diagrama já travado por outra pessoa é impedido e vê quem detém a Trava.
- [ ] Nessa mesma tela de bloqueio, o Usuário tem a opção de criar um Fork do Diagrama: gera um novo Diagrama independente (Slug próprio, Autor = quem forkou), com o conteúdo atual do original e sem vínculo funcional de volta a ele (metadado de origem é só informativo).
- [ ] Sair do Editor (fechar/navegar para fora) libera a Trava explicitamente.
- [ ] A Trava é liberada automaticamente após um período de inatividade sem atividade/heartbeat do Editor.
- [ ] Testes de Feature cobrem: aquisição ao abrir, bloqueio de um segundo Usuário, indicação na listagem, fork a partir da tela de bloqueio, liberação explícita, liberação por inatividade (usando manipulação de tempo, não `sleep`).
</content>
