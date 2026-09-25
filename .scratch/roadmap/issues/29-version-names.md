# 29: Every Version has a name

**What to build:** Versions are identified as "Version N", numbered sequentially per Diagram, and anyone who can edit the Diagram can rename one to mark a milestone; renamed Versions stand out in the history.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Existing Versions are numbered retroactively in chronological order; each new save gets the next number.
- [ ] Restoring produces the next number; a Fork starts at Version 1.
- [ ] Anyone who can edit the Diagram can rename a Version; names needn't be unique; no Git tags are used (ADR-0004).
- [ ] The history shows each Version's name and highlights renamed ones.
- [ ] Feature tests for numbering, restore numbering, renaming and permission refusal.
