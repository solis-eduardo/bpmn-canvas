# Versionamento de Diagramas usando Git internamente, sem expor Git ao usuário

O legado guardava só a versão atual do arquivo `.bpmn` — sobrescrita a cada save, sem histórico. O próprio README do legado sugeria, como paliativo manual, versionar a pasta `diagrams/` com Git via cron externo.

Formalizamos essa ideia como parte do produto: cada save de um Diagrama gera uma Versão internamente, implementada como um commit Git no arquivo em disco (retendo tudo, sem descartar saves intermediários). Ao usuário, o histórico é exposto de forma agregada por dia — sem nenhum vocabulário ou mecânica de Git visível — e uma Versão antiga pode ser restaurada, o que cria uma nova Versão no topo do histórico com aquele conteúdo (estilo "revert", nunca descartando o que veio depois).

Escolhemos Git como motor de armazenamento (em vez de uma tabela de versões bespoke no banco, guardando o XML completo por linha) porque já é eficiente para snapshots de texto incrementais e porque o próprio legado já apontava essa direção como estratégia de backup — só que agora automatizada e integrada à experiência do produto, em vez de um cron externo.
</content>
