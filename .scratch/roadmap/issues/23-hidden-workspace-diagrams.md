# 23: Diagrams inside a Workspace are hidden from outsiders

**What to build:** A Diagram in a Workspace becomes invisible to Users who are neither Members nor have direct access (Diagram Owner, Collaborator); Administrators still see everything (ADR-0006). Diagrams outside Workspaces stay visible to all.

**Blocked by:** 22 (Put a Diagram into a Workspace; Members edit it)

**Status:** ready-for-agent

- [ ] The list omits hidden Diagrams for Users without access; Collaborators who aren't Members still see them.
- [ ] Direct URL access to a hidden Diagram answers 404 (Editor, content, Versions, fork, request access), never 403.
- [ ] Administrators see and open every Diagram.
- [ ] Shares of a hidden Diagram keep working in the Public Viewer.
- [ ] The "request Collaborator access" action is only offered on visible Diagrams.
- [ ] Feature tests cover each visibility case, including 404 on every Diagram route and Shares still working.
