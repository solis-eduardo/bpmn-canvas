# BPMN Canvas

Internal, self-hosted BPMN 2.0 process modeling tool, with Keycloak authentication, per-user authorship/collaboration, and public sharing via unguessable, expiring links.

## Language

**Diagram**:
A BPMN 2.0 process modeled in the Editor. Persisted as a `.bpmn` file (XML) on disk (`disk_path`), with a corresponding database record (`slug`, `title`, `author_id`, timestamps). Has exactly one Author, exactly one Diagram Owner, zero or more Collaborators, and belongs to at most one Workspace.
_Avoid_: Flow (collides with "Sequence Flow", a native BPMN element), process, file

**Slug**:
Readable, unique identifier for a Diagram, used in the Editor's URL and as the name of the directory holding the Diagram's file (`<slug>/diagram.bpmn`). It's mutable: renaming also renames that directory (the file itself is always `diagram.bpmn`). The previous Slug stays permanently reserved and redirects to the current Diagram — it's never reused by another Diagram. If creating a new Diagram collides with a reserved Slug, a random alphanumeric suffix is appended automatically (creation is never blocked by this).
_Avoid_: File name, ID

**Author**:
The User who created a Diagram. Fixed forever — authorship is never transferred. By default the Author is also the Diagram Owner, but the two can diverge. Being the Author grants no powers by itself — it's credit only.
_Avoid_: creator, Owner (always qualify: Diagram Owner or Workspace Owner)

**Diagram Owner**:
The User currently responsible for a Diagram: the only one who can delete it, invite/remove Diagram Managers (and promote/demote Collaborators), and put it into a Workspace (one they're a Member of) or take it out of one. Starts as the Author and, unlike authorship, can be transferred to another User by the current Diagram Owner or an Administrator — no acceptance needed; the recipient is notified and needn't be a Member of the Diagram's Workspace. On transfer, the previous Diagram Owner becomes a plain Collaborator (never a Diagram Manager automatically), unless they choose to give up access.
_Avoid_: Owner (unqualified), Author (the Author is fixed; the Diagram Owner can change)

**Collaborator**:
A User with direct permission to edit a Diagram they're not the Diagram Owner of. Editing permission is the same for every Collaborator; the only distinction is the optional Diagram Manager role (below). Granted two ways: the Diagram Owner (or a Diagram Manager) invites the User directly, or the User requests access and the Diagram Owner or a Diagram Manager approves it (always as a plain Collaborator); an Administrator can also grant it directly, without going through the Diagram Owner. Direct invitations and removals notify the affected User. A Diagram hidden inside a Workspace can't be found to request access — only invitation reaches it.
_Avoid_: Editor (a role, not to be confused with the Editor interface)

**Diagram Manager**:
A Collaborator the Diagram Owner promoted to also manage the Diagram's other Collaborators: invite them, approve or decline access requests (as plain Collaborators), and remove plain Collaborators. Hierarchy: Diagram Owner > Diagram Manager > Collaborator. Only the Diagram Owner (or an Administrator) can promote, demote or remove a Diagram Manager, or invite one; a Diagram Manager can leave on their own. Everything else (delete, ownership transfer, Workspace placement) stays with the Diagram Owner. Promotions, demotions, invitations and removals notify the affected User (ADR-0017).
_Avoid_: Manager (unqualified; collides with Administrator and Workspace Owner)

**Administrator**:
A User with the privilege to grant Collaborator access on any Diagram regardless of its Diagram Owner, transfer any Diagram's ownership, to create accounts manually, and to manage any Workspace (including transferring Workspace ownership). Sees every Diagram, including those hidden inside Workspaces they're not a Member of. The role can come from two coexisting sources: set manually by another Administrator, or granted automatically by the corresponding role/claim in the Keycloak token (see User). A claim-granted Administrator follows the claim at each Keycloak login: the claim's absence turns the role off, and it can only be revoked manually while the claim is present; a manually set Administrator is never turned off by the claim's absence (ADR-0005, as amended).

**Share**:
A link reachable by anyone holding its random, unguessable address, with an expiration (`expires_at`) set in days (default 7, max 60), granting read access to a Diagram without requiring login. A Share either follows the Diagram's latest Version or is pinned to one specific Version. Has a download permission (`allow_download`, disabled by default) and can be revoked before it expires (`revoked_at`). A Diagram can have multiple active Shares simultaneously, each with its own expiration/permission. The Diagram Owner and the Diagram's Collaborators create and revoke Shares (Workspace Members who are not Collaborators, and Administrators, cannot). A Share keeps working when its Diagram is hidden inside a Workspace — visibility in the Diagram list doesn't affect Shares.
_Avoid_: Share token, public link (use "Share" for the entity; the URL itself can be called a "share link")

**Editor**:
Authenticated interface for creating/editing a Diagram (bpmn-js Modeler). Accessible by the Diagram Owner, Collaborators, and Members of the Diagram's Workspace.

**Public Viewer**:
Read-only interface for viewing a Diagram by anyone with a valid Share link (not expired, not revoked), without authentication. Exports PNG/SVG/BPMN only if the Share allows download (`allow_download`).
_Avoid_: Viewer (without qualifying "public")

**Lock**:
State of a Diagram while it's held for editing by someone who can edit it, which prevents anyone else from opening the Editor at the same time. One User can hold Locks on multiple Diagrams. Shown as an indicator in the Diagram listing (indication only, doesn't block the listing itself). Navigation and closing the Editor do not release the Lock by themselves: the holder can explicitly release it, an Administrator can explicitly force-release it, and it expires on its own after 3 minutes without a Heartbeat (the holder's unsaved content is then saved as a provisional Version). A manual release or force-release with unsaved content asks whether to save it as a provisional Version too, or discard it. The Lock is also released as soon as its holder loses the right to edit the Diagram (removed as Collaborator, removed from or leaving its Workspace, the Diagram taken out of or moved between Workspaces, a Diagram Owner transfer that gives up access, or a Keycloak group sync that removes Workspace membership); the holder's unsaved draft is then dropped, not kept as a provisional Version, since a Version can't be authored by someone who may no longer edit. Since the Editor stops sending Heartbeats after 2 minutes idle, that means roughly 5 minutes of inactivity in practice. Anyone who tries to open the Editor of a locked Diagram is blocked, sees who holds the Lock, and is offered the option to create a copy (Fork). Opening a page never takes the Lock: the Editor acquires it with its own request once loaded, and a refused acquisition leads to the read-only Viewer. An already-expired Lock (not yet released by the expiry job) does not block that acquisition: the Editor takes it over at once, and the previous holder's unsaved draft is kept as a provisional Version exactly as if the expiry job had released it. Anyone eligible to edit can request the Lock; the holder is notified (both by a banner in their open Editor and by a notification, which reaches them once they've walked away; one notification per requester until the holder reads it, however often the request is repeated), but the request never transfers the Lock automatically on its own, including after 30 seconds — the holder or an Administrator may deliberately act on it with a Transfer. The requester remains blocked until the Lock is released, expires, or is transferred.
_Avoid_: Editing Lock (just "Lock")

**Heartbeat**:
A periodic renewal of a Lock, sent by its holder's open Editor to signal it's still in use; it stops while the holder is idle or the Editor is closed. It never changes who holds the Lock or grants it, but its absence for 3 minutes lets the Lock expire. While the Editor has unsaved changes, the Heartbeat also carries them, so they can be saved as a provisional Version on expiry or when the Lock is released by hand.

**Transfer (of a Lock)**:
A deliberate act by the current holder or an Administrator that hands the Lock directly to a chosen User eligible to edit the Diagram, skipping separate release+acquire steps. May respond to an existing request from that User, or be spontaneous (no prior request). Never automatic and never triggered by elapsed time — it always requires the holder or an Administrator to explicitly pick the recipient.
_Avoid_: Lock request (a request only notifies; only a Transfer changes the holder)

**Fork**:
A new, independent Diagram created from another one's content, available at any time to anyone who can open the origin, locked or not (running into a Lock merely offers it as the way to keep working). It is titled "Copy of <origin title>" (pt-BR: "Cópia de XXX"), the prefix localized in the application's default language (`app.locale`), not the forker's, and resolved once at fork time, so it doesn't change if the default language changes later. Has its own Slug (derived from that title), and the Author and Diagram Owner become whoever forked it; whoever forks chooses which Workspace (among those they're a Member of) the Fork is created in, or none; the origin can be kept as informational metadata, with no functional link (there's no merge between a Fork and the original).

**Version**:
Historical record of a Diagram's content at a saved moment. Each save generates a Version internally (via Git), retaining everything; day-based grouping is just a display concern for showing history to the User, without exposing the Git mechanics. Restoring an old Version creates a new Version on top of the history (revert-style) — it never discards intermediate Versions. Every Version has a name; by default it's "Version N", N being sequential within the Diagram, and anyone who can edit the Diagram can rename it to mark a milestone (e.g. "Approved by the board"). Names needn't be unique; the number still identifies the Version. Restoring creates Version N+1; a Fork starts again at Version 1. A Version kept by an expiring Lock is **provisional**: an explicit state (not a name), shown with a badge in the history until an editor makes it permanent (it becomes an ordinary Version) or deletes it. Deleting is a soft delete: the Version is hidden from the history but its stored record and number stay, so the other Versions keep their numbers and any Share or comparison referencing it keeps working. Only provisional Versions can be deleted; any editor can make one permanent or delete it. The history shows each Version's author and time, and any editor can rename a Version from there. The Editor remembers the Version its content was loaded from and names it on every save: if a newer Version exists by then (another tab, or a restore), the save is refused and the User chooses to compare the two, save the local edits as a Fork, reload the newer Version, save over it anyway, or keep editing. A newer Version that is the User's own unsaved content kept by their expired Lock is not a conflict. Saves to one Diagram's history happen one at a time; a save that finds the history still busy after about 30 seconds is not applied and the User is asked to try again (nothing is lost: the Editor keeps the unsaved changes, and an expiring Lock stays held until its next run).
_Avoid_: commit, revision, tag, label, milestone (a named Version is still just a Version)

**User**:
An authenticated account in the system. Each User has exactly one login method, recorded on the account: local login or Keycloak SSO login (OpenID Connect, with just-in-time provisioning on first access). Both methods coexist across Users, but a User only signs in with the one recorded. A manually created account starts with local login; an Administrator can change the method deliberately afterwards, on the edit form (Keycloak accounts need no password), and is overwritten with Keycloak when a Keycloak login matches a local account by email. A local password stops being valid when the method is Keycloak but is kept, so switching back to local restores it. Keycloak only links to an existing account when it reports the email as verified (`email_verified`); otherwise the login is rejected. On every Keycloak login of an already linked account, the name and email are refreshed from the IdP; the email is only taken when verified and not held by another account. Replaces the legacy stack's single shared login (Basic Auth). An Administrator can deactivate a User: they can't sign in by either method, their sessions end, their Locks are released (drafts kept as provisional Versions), and they're excluded from every listing, picker, lookup and validation by the default scope (only User administration shows them); their Diagrams and Workspaces stay owned by them until an Administrator transfers them. Authorship is unchanged, and the User can be reactivated (ADR-0020). Pickers list and search Users by name only, never showing emails, and the endpoints that target a User by email answer generically (ADR-0021).
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

**Comment**:
A message in a discussion about a Diagram, written in a Comment Thread. Comments are for reviewing a process ("is this gateway right?") and are never part of the BPMN content: they live in the database, are not Versions, are not restored with a Version, and are not copied into a Fork. Anyone who can open the Diagram can write one, whether or not they hold the Lock, because writing a Comment never changes the Diagram (ADR-0024). A Comment can't be edited or deleted; it goes away only with its Diagram.
_Avoid_: note, annotation (the BPMN `textAnnotation` is a native element of the Diagram's content)

**Comment Thread**:
A conversation of Comments about a Diagram as a whole or, optionally, about one BPMN element, identified by its element id and shown as a marker on that element in the canvas. Opened by the first Comment; any later Comment is a reply. Anyone who can open the Diagram can open, reply to, resolve or reopen a Thread. A resolved Thread stays listed (marked as resolved) and can be reopened; a new reply reopens it. A Thread whose element no longer exists in the Diagram's current content is **orphaned**: it stays listed, without a marker, and is shown as orphaned, so deleting an element never loses the discussion. A new Comment notifies the Diagram Owner and the Thread's other participants (the Users who already commented in it) through the existing notification and email preferences, never the one who wrote it and never a User who can no longer open the Diagram (ADR-0024).
_Avoid_: discussion, conversation, review (reserve for the activity), issue

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
| Diagram Manager | Gerente do Diagrama |
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
| Comment | Comentário |
| Comment Thread | Conversa de comentários |
| Orphaned (Thread) | Órfã |
| Resolve / Reopen (a Thread) | Resolver / Reabrir |
