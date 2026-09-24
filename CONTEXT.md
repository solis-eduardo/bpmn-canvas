# BPMN Studio

Ferramenta interna de modelagem de processos BPMN 2.0, self-hosted, com autenticação via Keycloak, autoria/colaboração por usuário e compartilhamento público via links assinados com expiração.

## Language

**Fluxo**:
Um processo BPMN 2.0 modelado no Editor. Persistido como arquivo `.bpmn` (XML) em disco (`disk_path`), com um registro correspondente no banco (`slug`, `title`, `owner_id`, timestamps). Tem exatamente um Usuário como Owner e zero ou mais Colaboradores.
_Avoid_: Diagrama, processo, arquivo

**Slug**:
Identificador legível e único do Fluxo, usado na URL do Editor (`/editor/{slug}`) e como base do nome do arquivo físico (`{slug}.bpmn`). É mutável: ao renomear, o arquivo físico é renomeado junto; links antigos para o slug anterior retornam 404 (sem histórico/redirect).
_Avoid_: Nome do arquivo, ID

**Owner**:
O Usuário dono de um Fluxo — quem o criou. Único que pode gerenciar Colaboradores e Compartilhamentos, e o único que pode excluir o Fluxo.
_Avoid_: Autor, criador (usar Owner para bater com `owner_id`/Policy)

**Colaborador**:
Usuário com permissão de editar um Fluxo do qual não é Owner. Permissão binária — não há distinção de papel (viewer/editor) entre Colaboradores.
_Avoid_: Editor (papel, não confundir com a interface Editor)

**Compartilhamento** (Share):
Link público, assinado e com expiração (`expires_at`), que dá acesso de leitura a um Fluxo sem exigir login. Tem uma permissão de download (`allow_download`) e pode ser revogado antes de expirar (`revoked_at`). Um Fluxo pode ter múltiplos Compartilhamentos ativos simultaneamente, cada um com sua própria expiração/permissão. Somente o Owner cria e revoga Compartilhamentos.
_Avoid_: Token de compartilhamento, link público (usar "Compartilhamento" para a entidade; a URL em si pode ser chamada de "link de compartilhamento")

**Editor**:
Interface autenticada de criação/edição de um Fluxo (bpmn-js Modeler). Acessível pelo Owner e por Colaboradores.

**Visualizador Público**:
Interface de leitura somente-leitura de um Fluxo por qualquer pessoa com um link de Compartilhamento válido (não expirado, não revogado), sem autenticação. Exporta PNG/SVG/BPMN apenas se o Compartilhamento permitir download (`allow_download`).
_Avoid_: Viewer (sem qualificar "público")

**Usuário**:
Conta autenticada via Keycloak, provisionada just-in-time no primeiro login (`keycloak_id` → registro local). Substitui o login único compartilhado (Basic Auth) do stack legado. Qualquer Usuário autenticado pode criar Fluxos (e vira seu Owner) — sem restrição por role/group do Keycloak nesta fase.
