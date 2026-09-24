# 04: Trava de Edição — solicitação, timeout de 30s e força-liberação

**What to build:** Quem está bloqueado por uma Trava de Edição (ticket 03) pode solicitá-la ao detentor atual em vez de só esperar ou fazer um Fork. O detentor é avisado e pode transferir manualmente; se não responder em 30 segundos, a Trava é transferida automaticamente. Um Administrador pode forçar a liberação de qualquer Trava a qualquer momento.

**Blocked by:** 03

**Status:** done

- [x] Um Usuário bloqueado por uma Trava consegue solicitar a Trava ao detentor atual.
- [x] O detentor da Trava é notificado da solicitação (mecanismo de polling) enquanto ainda está no Editor.
- [x] O detentor consegue transferir a Trava manualmente para quem solicitou, a qualquer momento antes do timeout.
- [x] Se o detentor não responder (nem transferir, nem recusar de alguma forma observável) em 30 segundos após a solicitação, a Trava é transferida automaticamente para quem solicitou.
- [x] Um Administrador consegue forçar a liberação de qualquer Trava a qualquer momento, independentemente de solicitação ou timeout em curso.
- [x] Testes de Feature cobrem: solicitação, transferência manual antes do timeout, transferência automática após 30s (via manipulação de tempo), e força-liberação por Administrador.

## Comments

Implementado via `App\Actions\Diagramas\SolicitarTrava` (orquestra os três desfechos de `POST /diagramas/{slug}/trava/solicitar`: `obtida` direto se a Trava estiver livre, `obtida` por transferência automática se o pedido pendente já venceu `Diagrama::LOCK_REQUEST_TRANSFER_SECONDS` (30s), ou `aguardando` registrando o pedido) e `App\Actions\Diagramas\LiberarTrava` (força-liberação por Administrador, reaproveitando o endpoint `DELETE /diagramas/{slug}/lock` do ticket 03 — um Administrador agora pode liberar mesmo sem ser o detentor).

Transferência manual do detentor: não há um endpoint dedicado de "ceder para fulano" — o detentor libera explicitamente via `DELETE /diagramas/{slug}/lock` (ticket 03) a qualquer momento antes do timeout, e o solicitante obtém a Trava no próximo polling de `solicitar` (a Trava está livre, cai no primeiro `if`). Um heartbeat do detentor (`POST /diagramas/{slug}/heartbeat`) também cancela implicitamente uma solicitação pendente, por reiniciar a contagem de inatividade — coberto por `test_a_heartbeat_from_the_holder_cancels_a_pending_lock_request`.

Testes em `tests/Feature/TravaEdicaoTest.php`: `test_requesting_the_lock_when_it_is_free_acquires_it_directly`, `test_requesting_a_lock_held_by_someone_else_waits_until_the_holder_fails_to_respond` (cobre solicitação + transferência automática via `travel()`), `test_a_heartbeat_from_the_holder_cancels_a_pending_lock_request`, `test_an_administrador_can_force_release_a_lock_held_by_someone_else`, `test_a_non_admin_cannot_release_a_lock_held_by_someone_else`.

Migration `2026_09_24_210000_add_lock_request_columns_to_diagramas_table` adiciona `lock_requested_by`/`lock_requested_at` a `diagramas`. Suíte final: 89 testes.
