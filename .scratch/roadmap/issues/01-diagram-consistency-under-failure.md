# 01: Diagram operations stay consistent under disk or database failure

**What to build:** Creating, renaming, deleting and forking a Diagram never leave the list showing a ghost or broken Diagram when the disk write or the database transaction fails halfway. The existing compensation logic is proven by tests and fixed wherever the tests reveal a gap.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Create: if writing the BPMN file fails, no Diagram record remains and the User sees a clear error.
- [ ] Rename: if the database transaction fails after the move on disk, the Diagram is back at its previous Slug and still opens through it.
- [ ] Delete: if removing the Diagram's directory fails, the record is already gone; the worst case is an orphan directory, never a record pointing at a missing file.
- [ ] Fork: if copying the content fails, no new Diagram is registered.
- [ ] Feature tests at the HTTP seam inject the failures (faked storage disk, exception inside the transaction) and never call the Diagram repository's internals directly.
