# BPMN Studio

Ferramenta interna de modelagem de processos BPMN 2.0, self-hosted, com autenticação via Keycloak, autoria/colaboração por usuário e compartilhamento público via links assinados com expiração.

## Language

**Diagrama**:
Um processo BPMN 2.0 modelado no Editor. Persistido como arquivo `.bpmn` (XML) em disco (`disk_path`), com um registro correspondente no banco (`slug`, `title`, `author_id`, timestamps). Tem exatamente um Usuário como Autor e zero ou mais Colaboradores.
_Avoid_: Fluxo (colide com "Fluxo de Sequência", elemento nativo do BPMN), processo, arquivo

**Slug**:
Identificador legível e único do Diagrama, usado na URL do Editor e como base do nome do arquivo físico. É mutável: ao renomear, o arquivo físico é renomeado junto. O Slug anterior fica reservado permanentemente e passa a redirecionar para o Diagrama atual — nunca é reaproveitado por outro Diagrama. Se a criação de um novo Diagrama colidir com um Slug reservado, um sufixo alfanumérico aleatório é anexado automaticamente (a criação nunca é bloqueada por isso).
_Avoid_: Nome do arquivo, ID

**Autor**:
O Usuário dono de um Diagrama — quem o criou. Único que pode excluir o Diagrama, convidar/remover Colaboradores e criar/revogar Compartilhamentos.
_Avoid_: Owner, criador (usar Autor para bater com o glossário)

**Colaborador**:
Usuário com permissão de editar um Diagrama do qual não é Autor. Permissão binária (não há distinção de papel entre Colaboradores). Concedida de duas formas: o Autor convida o Usuário diretamente, ou o Usuário solicita acesso e o Autor aprova; um Administrador também pode conceder diretamente, sem passar pelo Autor.
_Avoid_: Editor (papel, não confundir com a interface Editor)

**Administrador**:
Usuário com privilégio de conceder Colaborador em qualquer Diagrama independentemente do Autor, e de criar contas manualmente. O papel pode vir de duas origens que coexistem: setado manualmente por outro Administrador, ou concedido automaticamente pela role/claim correspondente no token do Keycloak (ver Usuário). Uma vez concedido, não é revogado pela ausência da claim — só uma origem manual desliga.

**Compartilhamento**:
Link público, assinado e com expiração (`expires_at`) definida em dias pelo Autor (padrão 7, máximo 60), que dá acesso de leitura a um Diagrama sem exigir login. Tem uma permissão de download (`allow_download`, desabilitada por padrão) e pode ser revogado antes de expirar (`revoked_at`). Um Diagrama pode ter múltiplos Compartilhamentos ativos simultaneamente, cada um com sua própria expiração/permissão. Somente o Autor cria e revoga Compartilhamentos.
_Avoid_: Token de compartilhamento, link público (usar "Compartilhamento" para a entidade; a URL em si pode ser chamada de "link de compartilhamento")

**Editor**:
Interface autenticada de criação/edição de um Diagrama (bpmn-js Modeler). Acessível pelo Autor e por Colaboradores.

**Visualizador Público**:
Interface de leitura somente-leitura de um Diagrama por qualquer pessoa com um link de Compartilhamento válido (não expirado, não revogado), sem autenticação. Exporta PNG/SVG/BPMN apenas se o Compartilhamento permitir download (`allow_download`).
_Avoid_: Viewer (sem qualificar "público")

**Trava de Edição**:
Estado de um Diagrama enquanto está sendo editado por um Autor ou Colaborador, que impede que outra pessoa abra o Editor simultaneamente. Aparece indicada na listagem de Diagramas (somente indicação, sem bloquear a listagem em si). Liberada explicitamente ao sair do Editor, ou por timeout de inatividade. Quem tenta abrir o Editor de um Diagrama travado é bloqueado, vê quem detém a Trava, e recebe a sugestão de criar uma cópia (Fork). Quem tenta editar pode solicitar a Trava (via polling): se o detentor não responder em 30 segundos, a Trava é transferida automaticamente para quem solicitou. Um Administrador também pode forçar a liberação a qualquer momento, como via alternativa ao mecanismo dos 30 segundos.

**Fork**:
Diagrama novo e independente criado a partir do conteúdo de outro no momento em que alguém esbarra em uma Trava de Edição. Tem Slug próprio e o Autor passa a ser quem forkou; a origem pode ser guardada como metadado informativo, sem vínculo funcional (não há merge entre Fork e original).

**Versão**:
Registro histórico do conteúdo de um Diagrama em um momento salvo. Cada save gera uma Versão internamente (via Git), retendo tudo; a agregação por dia é apenas uma forma de exibição do histórico ao Usuário, sem expor a mecânica Git. Restaurar uma Versão antiga cria uma nova Versão no topo do histórico com aquele conteúdo (estilo "revert") — nunca descarta as Versões intermediárias.

**Usuário**:
Conta autenticada no sistema. Duas formas de login coexistem, e o Usuário escolhe qual usar — não é uma migração de uma para outra: login local (conta provisionada manualmente por um Administrador) e login via Keycloak SSO (OpenID Connect), com provisionamento just-in-time no primeiro acesso. Uma conta local e uma identidade Keycloak com o mesmo e-mail são vinculadas automaticamente à mesma conta, nunca duplicadas. Substitui o login único compartilhado (Basic Auth) do stack legado.

**Ambiente de Trabalho** _(futuro, não implementado nesta fase)_:
Contexto de agrupamento de Usuários e Diagramas planejado para uma fase futura. Diagramas de um Ambiente seriam automaticamente visíveis/editáveis (nível Colaborador) por todos os membros, sem passar pelo fluxo de Colaborador. Nesta fase, cada Usuário é implicitamente seu próprio Ambiente, sem nenhum compartilhamento automático.
</content>
