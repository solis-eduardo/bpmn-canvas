# Diagram Owner separate from Author, and Workspaces that hide their Diagrams

Status: accepted

Phase 2 introduces Workspaces (groups of Users that hold Diagrams). Designing them forced two decisions that change the access model from Phase 1 and are expensive to reverse once real Diagrams live inside Workspaces.

**Diagram Owner is a separate, transferable role; the Author is credit only.** In Phase 1 the Author (creator) held every management power: deleting the Diagram, managing Collaborators, managing Shares. People leave teams and responsibilities move, so those powers now belong to the **Diagram Owner**, which starts as the Author and can be transferred by the current Diagram Owner or an Administrator, without acceptance (the recipient is notified). The Author never changes and grants no powers by itself. On transfer, the previous Diagram Owner becomes a Collaborator by default, with an opt-out to give up access entirely. We rejected making the Author itself transferable, because that rewrites history: "who created this" is a fact, and "who is responsible now" is a role. We also rejected "the Author always keeps access", because it creates access that nobody can revoke.

**A Diagram inside a Workspace is hidden from everyone without access.** Phase 1 shows every Diagram to every authenticated User. A Diagram placed in a Workspace is now visible only to its Members, to Users with direct access (the Diagram Owner and Collaborators, even if they're not Members), and to Administrators. A Diagram outside any Workspace stays visible to all. The consequence is that the Phase 1 "request Collaborator access" flow can't reach hidden Diagrams, so the only way in is an invitation (by the Diagram Owner) or membership (added by the Workspace Owner, or mirrored from a Keycloak group an Administrator linked). We rejected "Workspaces grant editing but hide nothing", because teams need a private space. We rejected discoverable Workspaces with join requests, because that adds an approval flow and weakens "hidden".

## Consequences

- Any User can create a Workspace and becomes its **Workspace Owner**. Like the Diagram Owner, this role is transferable and needs no acceptance. A Workspace Owner can't leave without transferring ownership first, and a Workspace can't be deleted while it still holds Diagrams. This avoids silently exposing hidden Diagrams or deleting other people's work.
- "Owner" is never used unqualified. It's always either Diagram Owner or Workspace Owner.
- A Member is not a Collaborator. Leaving the Workspace removes the access, unless the User is also a direct Collaborator.
- When someone forks, they choose the Workspace the Fork lands in (one they're a Member of), or none.
- Keycloak group mirroring adds and removes only the Members that came from the group. Manually added Members are never touched, in line with ADR-0005's rule that automation never overrides manual grants.
- Shares ignore visibility: a Share link keeps working for a hidden Diagram.
