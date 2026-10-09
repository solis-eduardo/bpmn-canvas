# BPMN Canvas

Internal, self-hosted BPMN 2.0 process modeling tool, with Keycloak authentication, per-user authorship/collaboration, and public sharing via signed, expiring links.

## Language

**Diagram**:
A BPMN 2.0 process modeled in the Editor. Persisted as a `.bpmn` file (XML) on disk (`disk_path`), with a corresponding database record (`slug`, `title`, `author_id`, timestamps). Has exactly one Author, exactly one Diagram Owner, zero or more Collaborators, and belongs to at most one Workspace.
_Avoid_: Flow (collides with "Sequence Flow", a native BPMN element), process, file

**Slug**:
Readable, unique identifier for a Diagram, used in the Editor's URL and as the base of the physical file name. It's mutable: renaming also renames the physical file. The previous Slug stays permanently reserved and redirects to the current Diagram — it's never reused by another Diagram. If creating a new Diagram collides with a reserved Slug, a random alphanumeric suffix is appended automatically (creation is never blocked by this).
_Avoid_: File name, ID

**Author**:
The User who created a Diagram. Fixed forever — authorship is never transferred. By default the Author is also the Diagram Owner, but the two can diverge. Being the Author grants no powers by itself — it's credit only.
_Avoid_: creator, Owner (always qualify: Diagram Owner or Workspace Owner)

**Diagram Owner**:
The User currently responsible for a Diagram: the only one who can delete it, invite/remove Collaborators, create/revoke Shares, and put it into a Workspace (one they're a Member of) or take it out of one. Starts as the Author and, unlike authorship, can be transferred to another User by the current Diagram Owner or an Administrator — no acceptance needed; the recipient is notified and needn't be a Member of the Diagram's Workspace. On transfer, the previous Diagram Owner becomes a Collaborator, unless they choose to give up access.
_Avoid_: Owner (unqualified), Author (the Author is fixed; the Diagram Owner can change)

**Collaborator**:
A User with direct permission to edit a Diagram they're not the Diagram Owner of. Binary permission (there's no role distinction among Collaborators). Granted two ways: the Diagram Owner invites the User directly, or the User requests access and the Diagram Owner approves it; an Administrator can also grant it directly, without going through the Diagram Owner. Direct invitations and removals notify the affected User. A Diagram hidden inside a Workspace can't be found to request access — only invitation reaches it.
_Avoid_: Editor (a role, not to be confused with the Editor interface)

**Administrator**:
A User with the privilege to grant Collaborator access on any Diagram regardless of its Diagram Owner, transfer any Diagram's ownership, to create accounts manually, and to manage any Workspace (including transferring Workspace ownership). Sees every Diagram, including those hidden inside Workspaces they're not a Member of. The role can come from two coexisting sources: set manually by another Administrator, or granted automatically by the corresponding role/claim in the Keycloak token (see User). Once granted, it isn't revoked by the claim's absence — only a manual origin turns it off.

**Share**:
A public, signed link with an expiration (`expires_at`) set in days (default 7, max 60), granting read access to a Diagram without requiring login. A Share either follows the Diagram's latest Version or is pinned to one specific Version. Has a download permission (`allow_download`, disabled by default) and can be revoked before it expires (`revoked_at`). A Diagram can have multiple active Shares simultaneously, each with its own expiration/permission. Only the Diagram Owner creates and revokes Shares. A Share keeps working when its Diagram is hidden inside a Workspace — visibility in the Diagram list doesn't affect Shares.
_Avoid_: Share token, public link (use "Share" for the entity; the URL itself can be called a "share link")

**Editor**:
Authenticated interface for creating/editing a Diagram (bpmn-js Modeler). Accessible by the Diagram Owner, Collaborators, and Members of the Diagram's Workspace.

**Public Viewer**:
Read-only interface for viewing a Diagram by anyone with a valid Share link (not expired, not revoked), without authentication. Exports PNG/SVG/BPMN only if the Share allows download (`allow_download`).
_Avoid_: Viewer (without qualifying "public")

**Lock**:
State of a Diagram while it's held for editing by someone who can edit it, which prevents anyone else from opening the Editor at the same time. One User can hold Locks on multiple Diagrams. Shown as an indicator in the Diagram listing (indication only, doesn't block the listing itself). Navigation and closing the Editor do not release the Lock by themselves: the holder can explicitly release it, an Administrator can explicitly force-release it, and it expires on its own after 5 minutes without a Heartbeat (the holder's unsaved content is then saved as a provisional Version). Since the Editor stops sending Heartbeats after about 3 minutes idle, that means roughly 8 minutes of inactivity in practice. Anyone who tries to open the Editor of a locked Diagram is blocked, sees who holds the Lock, and is offered the option to create a copy (Fork). Anyone eligible to edit can request the Lock; the holder is notified (both by a banner in their open Editor and by a notification, which reaches them once they've walked away), but the request never transfers the Lock automatically on its own, including after 30 seconds — the holder or an Administrator may deliberately act on it with a Transfer. The requester remains blocked until the Lock is released, expires, or is transferred.
_Avoid_: Editing Lock (just "Lock")

**Heartbeat**:
A periodic renewal of a Lock, sent by its holder's open Editor to signal it's still in use; it stops while the holder is idle or the Editor is closed. It never changes who holds the Lock or grants it, but its absence for 5 minutes lets the Lock expire. While the Editor has unsaved changes, the Heartbeat also carries them, so they can be saved as a provisional Version on expiry.

**Transfer (of a Lock)**:
A deliberate act by the current holder or an Administrator that hands the Lock directly to a chosen User eligible to edit the Diagram, skipping separate release+acquire steps. May respond to an existing request from that User, or be spontaneous (no prior request). Never automatic and never triggered by elapsed time — it always requires the holder or an Administrator to explicitly pick the recipient.
_Avoid_: Lock request (a request only notifies; only a Transfer changes the holder)

**Fork**:
A new, independent Diagram created from another one's content at the moment someone runs into a Lock. Has its own Slug, and the Author and Diagram Owner become whoever forked it; whoever forks chooses which Workspace (among those they're a Member of) the Fork is created in, or none; the origin can be kept as informational metadata, with no functional link (there's no merge between a Fork and the original).

**Version**:
Historical record of a Diagram's content at a saved moment. Each save generates a Version internally (via Git), retaining everything; day-based grouping is just a display concern for showing history to the User, without exposing the Git mechanics. Restoring an old Version creates a new Version on top of the history (revert-style) — it never discards intermediate Versions. Every Version has a name; by default it's "Version N", N being sequential within the Diagram, and anyone who can edit the Diagram can rename it to mark a milestone (e.g. "Approved by the board"). Names needn't be unique; the number still identifies the Version. Restoring creates Version N+1; a Fork starts again at Version 1. A Version kept by an expiring Lock is **provisional**: an explicit state (not a name), shown with a badge in the history until an editor makes it permanent (it becomes an ordinary Version) or deletes it. Deleting is a soft delete: the Version is hidden from the history but its stored record and number stay, so the other Versions keep their numbers and any Share or comparison referencing it keeps working. Only provisional Versions can be deleted; any editor can make one permanent or delete it. The history shows each Version's author and time, and any editor can rename a Version from there.
_Avoid_: commit, revision, tag, label, milestone (a named Version is still just a Version)

**User**:
An authenticated account in the system. Each User has exactly one login method, recorded on the account: local login or Keycloak SSO login (OpenID Connect, with just-in-time provisioning on first access). Both methods coexist across Users, but a User only signs in with the one recorded. A manually created account starts with local login; an Administrator can change the method deliberately afterwards, on the edit form (Keycloak accounts need no password), and is overwritten with Keycloak when a Keycloak login matches a local account by email. A local password stops being valid when the method is Keycloak but is kept, so switching back to local restores it. Keycloak only links to an existing account when it reports the email as verified (`email_verified`); otherwise the login is rejected. Replaces the legacy stack's single shared login (Basic Auth).
_Avoid_: using both methods on one account

**Workspace**:
A group of Users (its Members) that holds Diagrams. Any User can create a Workspace and becomes its Workspace Owner. An Administrator may link it to a Keycloak group; those group-managed memberships follow the User's groups on login without changing manually managed memberships. A Diagram belongs to at most one Workspace; while it does, every Member can edit it as if they were a Collaborator, and it is hidden from Users who are neither Members nor have direct access to it (Diagram Owner, Collaborator) — a Collaborator keeps seeing the Diagram even without being a Member. A Diagram outside any Workspace is reachable by every User, but the Diagram list shows only what a User can open: the others surface through search as discoverable Diagrams, exposing just their title, Diagram Owner and an access-request action (ADR-0013).
_Avoid_: group, team, environment, folder

**Member**:
A User who belongs to a Workspace. Membership grants Collaborator-level editing on the Workspace's Diagrams, but a Member is not a Collaborator: leaving the Workspace removes that access, unless the User is also a Collaborator of the Diagram directly. Members are added by the Workspace Owner or, when an Administrator links the Workspace to a Keycloak group, mirrored from that group (manually added Members are never removed by the mirror).
_Avoid_: Workspace Collaborator

**Workspace Owner**:
The single User who manages a Workspace: adds and removes Members, renames and deletes it, and can take any Diagram out of it (only a Diagram Owner puts a Diagram in). Starts as the creator; the Workspace Owner or an Administrator can transfer it to another User, with no acceptance needed (the recipient is notified). Can't leave the Workspace without transferring it first. A Workspace can't be deleted while it still holds Diagrams.
_Avoid_: Owner (unqualified), Workspace admin, manager (reserve "Administrator" for the system-wide role)

**Tag**:
A global, free-text marker applied to Diagrams to organize and filter the Diagram list. Anyone who can edit a Diagram (Diagram Owner, Collaborator, Member) can apply or remove its Tags. Unrelated to the Git mechanics behind Versions, which stay invisible.
_Avoid_: label, category, folder

## PT-BR UI terms

The UI is localized in pt-BR (`lang/pt_BR`); code, docs and commits use the English term. Keep these pairings when adding or translating UI strings.

| English | PT-BR |
|---|---|
| Diagram | Diagrama |
| Slug / Old slug | Slug / Slug antigo |
| Author | Autor |
| Diagram Owner | Dono do Diagrama |
| Collaborator | Colaborador |
| Administrator | Administrador |
| Share | Compartilhamento |
| Editor | Editor |
| Public Viewer | Visualizador Público |
| Lock (request, release, transfer) | Trava (solicitar, liberar, transferir) |
| Fork | Fork |
| Version / Version name | Versão / Nome da Versão |
| Restore (a Version) | Restaurar |
| User | Usuário |
| Workspace | Ambiente de Trabalho |
| Workspace Owner | Dono do Ambiente |
| Member | Membro |
| Tag | Tag |
