---
status: superseded by ADR-0013
---

# Separate the editable Diagram list from Diagram discovery

The main Diagram list contains only Diagrams whose Editors the User can open, while a separate, title-searchable discovery area exposes only the title and Diagram Owner for out-of-Workspace Diagrams and offers a request for Collaborator access. Discovery never grants Editor or content access; Diagrams inside Workspaces remain hidden from non-Members without direct access, and Administrators retain their existing visibility. This narrows ADR-0006's use of "visible" for out-of-Workspace Diagrams: they remain discoverable to everyone but appear in the main list only to Users who can open them. Separating discovery preserves the access-request path without presenting inaccessible Diagrams as usable work in the main list.
