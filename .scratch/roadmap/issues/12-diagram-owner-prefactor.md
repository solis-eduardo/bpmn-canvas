# 12: Diagram Owner takes over the Author's powers

**What to build:** Every Diagram gets a Diagram Owner, initially its Author, and the management powers move to it: deleting the Diagram, managing Collaborators and approving/refusing requests, and managing Shares. The Author stays as credit only (ADR-0006). Nothing changes for Users until someone transfers ownership.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] Existing Diagrams are backfilled with the Author as Diagram Owner; new Diagrams and Forks start with their creator as both.
- [ ] Every permission that checked "is the Author" now checks "is the Diagram Owner". Managing Collaborators stays Diagram Owner or Administrator; managing Shares stays Diagram Owner only.
- [ ] The list and the Diagram screen show the Diagram Owner next to the Author.
- [ ] Feature tests: when Diagram Owner and Author differ, only the Diagram Owner can delete, manage Collaborators and manage Shares.
