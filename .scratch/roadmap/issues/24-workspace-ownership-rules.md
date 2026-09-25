# 24: Transfer Workspace ownership; leave and delete rules

**What to build:** Workspaces survive their creators: the Workspace Owner or an Administrator transfers ownership with no acceptance; the Workspace Owner can't leave without transferring first; a Workspace holding Diagrams can't be deleted.

**Blocked by:** 21 (Create a Workspace and manage its Members)

**Status:** ready-for-agent

- [ ] The Workspace Owner or an Administrator can transfer ownership to another User, who becomes Workspace Owner immediately (and a Member, if not already).
- [ ] The Workspace Owner is refused when trying to leave without transferring first.
- [ ] Deleting a Workspace is refused while it holds Diagrams and allowed once it's empty.
- [ ] Feature tests for transfer, the leave refusal and the delete refusal.
