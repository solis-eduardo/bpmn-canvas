# Collaborators create and revoke Shares

Status: accepted

ADR-0006 made creating and revoking Shares a Diagram Owner-only power. In practice a Collaborator who edits a Diagram often needs a public link and had to ask the Diagram Owner each time.

**Decision:** `DiagramPolicy::manageShares` now allows the Diagram Owner **and** direct, approved Collaborators of the Diagram. This covers listing, creating and revoking Shares (a Collaborator can revoke any Share of the Diagram, including ones created by others). The Share panel on the Diagram screen is shown to the same Users.

**Unchanged:** Workspace Members who are not direct Collaborators get no Share power; Administrators get no special Share power; Collaborator management, deletion, ownership transfer and Workspace placement stay as in ADR-0006.

Amends the Share part of ADR-0006.
