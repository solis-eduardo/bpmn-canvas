# 04: Trava de Edição — solicitação, timeout de 30s e força-liberação

**What to build:** Quem está bloqueado por uma Trava de Edição (ticket 03) pode solicitá-la ao detentor atual em vez de só esperar ou fazer um Fork. O detentor é avisado e pode transferir manualmente; se não responder em 30 segundos, a Trava é transferida automaticamente. Um Administrador pode forçar a liberação de qualquer Trava a qualquer momento.

**Blocked by:** 03

**Status:** ready-for-agent

- [ ] Um Usuário bloqueado por uma Trava consegue solicitar a Trava ao detentor atual.
- [ ] O detentor da Trava é notificado da solicitação (mecanismo de polling) enquanto ainda está no Editor.
- [ ] O detentor consegue transferir a Trava manualmente para quem solicitou, a qualquer momento antes do timeout.
- [ ] Se o detentor não responder (nem transferir, nem recusar de alguma forma observável) em 30 segundos após a solicitação, a Trava é transferida automaticamente para quem solicitou.
- [ ] Um Administrador consegue forçar a liberação de qualquer Trava a qualquer momento, independentemente de solicitação ou timeout em curso.
- [ ] Testes de Feature cobrem: solicitação, transferência manual antes do timeout, transferência automática após 30s (via manipulação de tempo), e força-liberação por Administrador.
</content>
