# 06: Colaboração

**What to build:** Um Diagrama passa a ter um Autor e, opcionalmente, Colaboradores com permissão de editar. O Autor convida diretamente (acesso imediato) ou aprova solicitações de acesso; um Administrador também pode conceder acesso independentemente do Autor. Editar um Diagrama (abrir o Editor, salvar) passa a exigir ser o Autor ou um Colaborador aprovado.

**Blocked by:** 02

**Status:** done

- [x] Cada Diagrama tem um Autor (quem o criou).
- [x] O Autor consegue convidar diretamente outro Usuário como Colaborador; o acesso é concedido imediatamente, sem etapa de aceite.
- [x] Um Usuário consegue solicitar acesso de Colaborador a um Diagrama que não é seu.
- [x] O Autor consegue ver e aprovar (ou recusar) solicitações pendentes de acesso ao seu Diagrama.
- [x] Um Administrador consegue conceder acesso de Colaborador em qualquer Diagrama, independentemente do Autor.
- [x] O Autor consegue remover um Colaborador do seu Diagrama a qualquer momento.
- [x] Abrir o Editor e salvar um Diagrama só é permitido para o Autor ou para um Colaborador com acesso aprovado; qualquer outro Usuário autenticado é barrado dessas ações (a listagem geral do Diagrama pode continuar visível a todos, como no ticket 02 — refinar visibilidade de listagem fica fora deste ticket se não houver necessidade explícita).
- [x] Testes de Feature cobrem: convite direto, solicitação + aprovação, recusa, concessão por Administrador, remoção, e bloqueio de edição para quem não é Autor nem Colaborador aprovado.

## Comments

Implementado via TDD (13 testes de Feature novos, seam HTTP + banco, `tests/Feature/ColaboracaoTest.php`) — suíte total: 49 testes (37 pré-existentes + 12 de Colaboração; o 13º arquivo de teste soma junto no total do `php artisan test`).

**Modelo de dados:** migration `create_diagrama_colaboradores_table` — `diagrama_id` (FK `diagramas`, cascade), `usuario_id` (FK `users`, cascade), `status` (string), `created_at` (sem `updated_at`, conforme spec — `DiagramaColaborador::UPDATED_AT = null`), único em `(diagrama_id, usuario_id)`. Model `App\Models\DiagramaColaborador` com constantes `STATUS_APROVADO`/`STATUS_SOLICITADO`. Nova relação `Diagrama::colaboradores()`.

**Autorização de edição:** `App\Policies\DiagramaPolicy` com `view`/`update` (Autor OU linha `aprovado` em `diagrama_colaboradores`), registrada em `AppServiceProvider::boot()` via `Gate::policy(Diagrama::class, DiagramaPolicy::class)` — mesmo padrão do Gate `manage-users` já existente. Aplicada com `$this->authorize(...)` só nos dois pontos pedidos: `DiagramaController::show` (abrir o Editor) e `DiagramaController::save` (salvar conteúdo); `index` (listagem) e `content` (fetch de conteúdo usado internamente pelo Editor já protegido por `show`) não foram tocados, mantendo a listagem geral visível a todo Usuário autenticado como no ticket 02.

**Endpoints novos** (`App\Http\Controllers\DiagramaColaboradorController`, registrados em `routes/web.php` dentro do grupo `auth`):
- `GET /diagramas/{slug}/colaboradores` — Autor ou Administrador listam Colaboradores/solicitações (qualquer status).
- `POST /diagramas/{slug}/colaboradores` — endpoint único para convite direto e solicitação (`user_id` ou `email` no payload). Ver regra exata abaixo.
- `PUT /diagramas/{slug}/colaboradores/{user}` — Autor ou Administrador aprovam uma solicitação pendente (`status` → `aprovado`).
- `DELETE /diagramas/{slug}/colaboradores/{user}` — Autor ou Administrador apagam a linha; reaproveitado tanto para "recusar" uma solicitação pendente quanto para "remover" um Colaborador já aprovado (ambos são, na prática, apagar a linha — distinção é só de rótulo/momento na UI).

**Regra exata convite-vs-solicitação (endpoint único, `POST /diagramas/{slug}/colaboradores`):** se quem faz a request é o Autor do Diagrama (`request->user()->id === diagrama->author_id`) OU tem `is_admin = true`, a linha é gravada/atualizada direto como `aprovado` — não importa quem seja o alvo (`user_id`/`email` do payload), cobrindo tanto "Autor convida terceiro" quanto "Autor/Admin se autoadicionando" (caso de baixo interesse prático, mas não bloqueado). Caso contrário (quem chama não é Autor nem Administrador), só é permitido quando o alvo do payload é o próprio chamador (auto-solicitação: `solicitante->id === alvo->id`), gravada como `solicitado`; qualquer tentativa de um não-Autor/não-Administrador adicionar outra pessoa diferente de si mesmo recebe 403. Optei por um único endpoint (em vez de dois separados para "convidar" e "solicitar") porque a spec já descreve a mesma tabela/transição de estado para os dois fluxos e a distinção só depende de comparar identidade de quem chama vs. identidade do Autor/alvo — não haveria payload ou validação diferente entre eles que justificasse rotas separadas.

**Métodos de `DiagramaController` tocados:** nenhum reescrito — apenas duas linhas (`$this->authorize('view', $diagrama)` em `show`, `$this->authorize('update', $diagrama)` em `save`) adicionadas nos métodos existentes, sem alterar assinatura, fluxo de resolução de slug ou qualquer outro comportamento. `routes/web.php` e `AppServiceProvider.php` só ganharam linhas novas (nenhuma linha existente reestruturada).

Rodado `mattpocock-skills:code-review` (Standards + Spec) após o merge. Achados corrigidos:
- **Spec (crítico)**: `GET /diagramas/{slug}/conteudo` não tinha `authorize()` — um Usuário barrado em `show` (403) conseguia ler o XML completo chamando essa rota direto. Adicionado `$this->authorize('view', $diagrama)`.
- **Standards**: a regra "Autor-ou-Admin" estava duplicada em 3 lugares (`DiagramaColaboradorController::authorizeGerenciamento()`, `$podeConcederDireto` em `store()`, e implicitamente fora da `DiagramaPolicy`). Extraída para uma ability `manage` na `DiagramaPolicy`, reaproveitada nos 3 pontos via `$this->authorize('manage', ...)`/`$user->can('manage', ...)`.
- **Standards (edge case)**: uma auto-solicitação de um Colaborador já `aprovado` podia rebaixá-lo para `solicitado` (efeito colateral do `updateOrCreate` sem checar o status atual). Corrigido para preservar `aprovado` quando já existente.
- **Integração com o ticket 03 (Trava de Edição, mergeado em paralelo)**: `DiagramaController::show()` agora aplica `authorize('view')` ANTES da lógica de trava — um Usuário sem permissão de editar (não-Autor, não-Colaborador aprovado) recebe 403 mesmo que o Diagrama não esteja travado por ninguém; só quem já tem permissão de editar chega a ver a tela de "travado por fulano". Os testes do ticket 03 que usavam um segundo usuário sem nenhuma relação com o Diagrama foram ajustados para torná-lo Colaborador aprovado primeiro, já que a tela de bloqueio só faz sentido entre pessoas que poderiam editar de qualquer forma.

+3 testes (autorização de `conteudo`, preservação de status aprovado) — suíte final: 59 testes.
</content>
