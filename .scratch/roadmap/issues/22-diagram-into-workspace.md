# 22: Put a Diagram into a Workspace; Members edit it

**What to build:** A Diagram Owner places a Diagram into one of their Workspaces, and every Member can then open the Editor and save it as a Collaborator would, with the Editing Lock still applying. The Diagram Owner or the Workspace Owner can take it out.

**Blocked by:** 21 (Create a Workspace and manage its Members)

**Status:** ready-for-agent

- [ ] The Diagram Owner can put the Diagram into a Workspace they're a Member of, and into at most one.
- [ ] The Diagram Owner or the Workspace Owner can take it out.
- [ ] Members can open the Editor and save; the Editing Lock applies to them as to anyone.
- [ ] A User removed from the Workspace loses edit access unless they're the Diagram Owner or a direct Collaborator.
- [ ] Feature tests cover put-in and take-out permissions, Member editing, and loss of access on removal.
