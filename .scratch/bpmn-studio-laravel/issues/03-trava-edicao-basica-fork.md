# 03: Trava de Edição básica + Fork

**What to build:** Abrir o Editor de um Diagrama adquire uma Trava de Edição que impede outra pessoa de abrir o mesmo Editor ao mesmo tempo. Quem esbarra na Trava vê quem a detém e pode criar um Fork (cópia independente do Diagrama) em vez de esperar. A Trava é liberada ao sair do Editor ou por inatividade. (A solicitação/transferência/timeout de 30s e a força-liberação por Administrador ficam para o ticket 04.)

**Blocked by:** 02

**Status:** ready-for-agent

- [x] Abrir o Editor de um Diagrama sem Trava ativa adquire a Trava para o Usuário atual.
- [x] A listagem de Diagramas indica quais estão com Trava ativa no momento.
- [x] Um Usuário que tenta abrir o Editor de um Diagrama já travado por outra pessoa é impedido e vê quem detém a Trava.
- [x] Nessa mesma tela de bloqueio, o Usuário tem a opção de criar um Fork do Diagrama: gera um novo Diagrama independente (Slug próprio, Autor = quem forkou), com o conteúdo atual do original e sem vínculo funcional de volta a ele (metadado de origem é só informativo).
- [x] Sair do Editor (fechar/navegar para fora) libera a Trava explicitamente.
- [x] A Trava é liberada automaticamente após um período de inatividade sem atividade/heartbeat do Editor.
- [x] Testes de Feature cobrem: aquisição ao abrir, bloqueio de um segundo Usuário, indicação na listagem, fork a partir da tela de bloqueio, liberação explícita, liberação por inatividade (usando manipulação de tempo, não `sleep`).

## Comments

Implementado via TDD (9 testes de Feature novos em `tests/Feature/TravaEdicaoTest.php`, seam HTTP + banco + disco, `Storage::fake` + `Carbon`/`travel()`; suíte final: 46 testes, todos passando, incluindo os 37 anteriores).

Migration `add_lock_columns_to_diagramas_table` (`locked_by` FK nullable em `users`, `locked_at`, `last_heartbeat_at`, ambos timestamp nullable). Lógica de Trava concentrada em `App\Models\Diagrama`: `hasActiveLock()`/`isLockedByOther()` (regra de timeout único, usada tanto no `show()` quanto na listagem), `acquireLock()`, `heartbeat()` (só efetiva se o chamador for o detentor atual — retorna `false` caso contrário, sem lançar exceção) e `releaseLock()` (idempotente; com Usuário informado só libera se for o detentor, senão no-op silencioso — decisão para simplificar a chamada de `unlock` no `beforeunload`, que não deveria falhar ruidosamente).

Timeout de inatividade: 90s, comparado contra `last_heartbeat_at` (ou `locked_at` se nunca houve heartbeat) — constante `Diagrama::LOCK_INACTIVITY_TIMEOUT_SECONDS`. Heartbeat do front-end a 30s (`setInterval` em `editor.js`). Esses dois valores já haviam sido decididos com o usuário nesta sessão (30s/90s), não são inferência.

`DiagramaController::show()` ganhou um parâmetro `Request $request` (não tinha antes) e, após `resolve()`, decide entre renderizar `diagramas.locked` (Diagrama travado por outro) ou adquirir/renovar a Trava e renderizar `diagramas.show` normalmente — nenhum outro método de CRUD existente foi alterado além disso. Reaproveitei o `resolve()` privado sem tocar nele.

Endpoints novos, todos via `resolve()`: `POST /diagramas/{slug}/heartbeat` (403 se quem chama não for o detentor atual), `DELETE /diagramas/{slug}/lock` (libera; no-op se não for o detentor), `POST /diagramas/{slug}/fork` (cria novo Diagrama com `Diagrama::uniqueSlug`, `author_id` = quem forkou, `forked_from_id` = original, copia o `.bpmn` atual do disco — segue o mesmo padrão de compensação disco/banco do commit "Ticket 02: corrige achado do code-review"; sem trava herdada, sem repositório Git próprio — isso fica para o ticket 05).

View nova `resources/views/diagramas/locked.blade.php` (resposta 200, não erro HTTP), com formulário de Fork. `index.blade.php` ganhou uma linha condicional indicando "— em edição por {nome}" quando `hasActiveLock()`. `show.blade.php`/`editor.js` ganharam `data-heartbeat-url`/`data-unlock-url`, polling de heartbeat a cada 30s e liberação da trava em `pagehide` (via `fetch` com `keepalive`).

**Decisão não 100% explícita:** ao reabrir o Editor sendo já o detentor atual (mesmo com heartbeat expirado, mas antes de outro usuário assumir a trava), tratei como renovação silenciosa da trava (chama `heartbeat()`, e só faz `acquireLock()` se `heartbeat()` falhar) em vez de forçar uma tela intermediária — evita que o próprio dono seja bloqueado pela própria trava expirada.

**Áreas de possível conflito de merge com tickets paralelos:**
- `app/Models/Diagrama.php`: só adições (constante, `casts()`, `lockedBy()` e os métodos de trava) — nenhum método existente foi reescrito.
- `app/Http/Controllers/DiagramaController.php`: `show()` teve a assinatura alterada (`+Request $request`) e ganhou ~10 linhas de lógica de trava no meio do método — é o ponto de maior risco de conflito, já que outros tickets (05/06/07) provavelmente também tocam `show()`. `index()` teve só o `with()` alterado (`'author'` → `['author', 'lockedBy']`). Os métodos novos (`heartbeat`, `unlock`, `fork`) foram inseridos entre `destroy()`'s posição original e o fim da classe, antes do `resolve()` privado.
- `routes/web.php`: 3 linhas novas de rota, sem tocar nas existentes.
- `resources/views/diagramas/index.blade.php`: um `@if` a mais dentro do `<li>` existente.
</content>
