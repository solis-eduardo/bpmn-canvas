Status: ready-for-agent

# BPMN Studio — Phase 2: Robustness, UX and Collaboration at scale

## Problem Statement

Phase 1 (spec `bpmn-studio-laravel`) delivered the core of BPMN Studio: User accounts, Diagrams with an Author and Collaborators, the Editing Lock with Fork, automatic Versions, and public Shares. It works, but it isn't yet the place where the organization keeps and evolves its processes:

- **Operational risk:** nothing backs up the Diagrams automatically (including their Version history, which lives next to them on disk), and nothing backs up the database either. Losing the host means losing everything. The app service has no health check, and Share links can come out as `http://` behind the HTTPS proxy. The disk/database compensation for creating, renaming, deleting and forking a Diagram exists, but no test has ever proved it under failure.
- **Raw screens:** screens are plain Blade with the legacy stylesheet. The Diagram list doesn't show enough to decide what to open, the Editing Lock screen doesn't explain the 30-second wait, and the Version history lives away from the Editor.
- **Rigid ownership:** the Author holds every management power forever. When the creator changes teams or leaves, nobody else can take responsibility for the Diagram.
- **Silent collaboration:** the Author only finds out about an access request by opening the Diagram. The requester only finds out about approval by trying again. Nobody is warned when a Share is about to expire.
- **Case-by-case collaboration, no privacy:** a team working together must invite Collaborators one by one on every Diagram, and every Diagram is visible to every User. A team can't have a private space.
- **Opaque history:** Versions are an undifferentiated "saved at" list. You can't see what changed between two Versions, you can't name an important one, and a Share always shows the latest content.
- **The list doesn't scale:** with dozens of Diagrams there's no search, filter, pagination or way to organize them.

## Solution

A second phase with seven fronts, delivered as independent vertical slices:

1. **Operational robustness:** tests that prove disk/database consistency under failure, health checks on every service, automated backup with a verified restore, and Share links that are always `https`.
2. **UX and visual design:** Tailwind + Vite (ADR-0007), a shared base layout, and a screen-by-screen redesign.
3. **Diagram Owner:** the management powers move from the fixed Author to a transferable Diagram Owner (ADR-0006).
4. **Notifications:** in-app notifications with history, for collaboration, ownership, Workspace and Share-expiry events, plus opt-in email.
5. **Workspaces:** any User can create a group of Members. Diagrams placed in a Workspace are editable by every Member and hidden from Users without access (ADR-0006).
6. **Named and comparable Versions:** every Version has a name ("Version N" by default, renamable). Versions can be compared visually, and a Share can be pinned to a Version or follow the latest.
7. **Search and organization:** search by title/Slug and by BPMN content, filters, pagination, and global Tags.

See `CONTEXT.md` for the glossary and ADR-0006/0007 for the decisions behind this spec.

## User Stories

**Operational robustness**

1. As a Diagram Owner, I want a creation that fails to write to disk to leave no "ghost" Diagram in the list, so that I never open something that doesn't exist.
2. As a Diagram Owner, I want a rename that fails in the database to bring the Diagram back to its previous Slug, so that links already circulating keep working.
3. As a Diagram Owner, I want deleting a Diagram to never leave a record pointing to a missing file, so that the list never shows a broken Diagram.
4. As a User, I want a Fork that fails while copying content to leave no empty Diagram in my name, so that I don't have to clean up garbage.
5. As an operator, I want the app service to have a health check, and the web service to wait for it, so that a deploy doesn't serve 502s while booting.
6. As an operator, I want automated, periodic backups of the Diagrams (including their Version history) and of the database, so that losing the host doesn't lose work.
7. As an operator, I want the disk and database backups taken in the same window, so that a restore never brings Diagrams without records or records without Diagrams.
8. As an operator, I want to set the backup destination and retention through environment variables, so that I can adapt infrastructure without changing code.
9. As an operator, I want a documented restore procedure verified on a clean environment, so that I trust the backup actually works.
10. As someone receiving a Share link, I want it to be `https`, so that my browser doesn't block it or warn about an insecure connection.

**UX and visual design**

11. As a User, I want a consistent header and navigation on every screen, so that I don't relearn each page.
12. As a User, I want the Diagram list to show the Author, the Diagram Owner, the last modification, and whether (and by whom) the Diagram is locked, so that I can decide what to open without clicking each one.
13. As a User who runs into an Editing Lock, I want to see clearly who holds it and since when, so that I can decide between waiting, requesting it, or forking.
14. As a User who requested the Lock, I want a 30-second countdown, so that I know when it'll be transferred to me.
15. As a User who runs into an Editing Lock, I want the Fork option on the same screen, so that I can keep working without waiting.
16. As the Lock holder, I want to see clearly in the Editor that someone requested the Lock, so that I can release it or keep going, knowing it transfers in 30 seconds.
17. As someone who can edit a Diagram, I want the Version history as a side panel inside the Editor, so that I can browse and restore Versions without leaving the Diagram.
18. As a Diagram Owner, I want a clear screen to manage Collaborators (invite, approve, refuse, remove), so that I understand who has access.
19. As a Diagram Owner, I want my active Shares shown legibly, with expiry, download permission and pinned Version, and a one-click copy of the link, so that I can manage what's exposed.
20. As someone receiving a Share link, I want a clean Public Viewer with the Diagram name, and export buttons only when allowed, so that I understand what I'm seeing without an account.
21. As a User, I want a login screen that makes both options clear (local account, and Keycloak when configured), so that I can choose without confusion.
22. As a User, I want the screens to work on smaller displays (laptop, tablet), so that I can consult Diagrams away from my workstation.

**Diagram Owner**

23. As a User, I want every existing Diagram to have its Author as its Diagram Owner, so that nothing changes until someone transfers ownership.
24. As a Diagram Owner, I want to be the one who deletes the Diagram, manages Collaborators and manages Shares, so that responsibility follows the current Diagram Owner and not the creator.
25. As a Diagram Owner, I want to transfer ownership to another User, so that someone else takes over when I change roles.
26. As a Diagram Owner transferring ownership, I want to stay on as a Collaborator by default, with a checkbox to give up access entirely, so that I choose whether I keep working on the Diagram.
27. As an Administrator, I want to transfer any Diagram's ownership, so that I can unblock Diagrams whose Diagram Owner left.
28. As the new Diagram Owner, I want to gain access without having to accept, even if I'm not a Member of the Diagram's Workspace, so that the handover never gets stuck.
29. As a User, I want the Author to stay credited forever, so that the history of who created a Diagram is never lost.

**Notifications**

30. As a Diagram Owner, I want a notification when someone requests Collaborator access to my Diagram, so that I can approve or refuse without discovering it by chance.
31. As a User, I want a bell with an unread count on every screen, so that I can see at a glance if there's something new.
32. As a User, I want to mark one or all notifications as read, so that the count stays meaningful.
33. As a User, I want clicking a notification to take me to the relevant Diagram or screen, so that I can act without navigating.
34. As a User, I want a screen listing my past notifications, read and unread, so that I can look something up after dismissing it.
35. As a requester, I want to be notified when my access request is approved or refused, so that I know whether I can start editing.
36. As a User, I want to be notified when I'm invited as a Collaborator or removed as one, so that my access never changes silently.
37. As a Diagram Owner, I want a warning when one of my Shares is about to expire, so that I can renew the link before its recipients lose access.
38. As a User, I want to be notified when I become the Diagram Owner of a Diagram or the Workspace Owner of a Workspace, so that I know I'm now responsible for it.
39. As a User, I want to be notified when I'm added to or removed from a Workspace, so that I learn about Diagrams I can now (or can no longer) see.
40. As a User, I want to opt into receiving notifications by email too, so that I'm warned even when BPMN Studio isn't open.
41. As a User, I want to turn notification email off at any time, so that I control what reaches my inbox.

**Workspaces**

42. As a User, I want to create a Workspace and become its Workspace Owner, so that my team has its own space.
43. As a Workspace Owner, I want to add and remove Members, so that the Workspace reflects the team.
44. As a Workspace Owner, I want to rename my Workspace, so that it keeps matching the team's name.
45. As a Workspace Owner or an Administrator, I want to transfer Workspace ownership to another User without acceptance, so that the Workspace survives its creator leaving.
46. As a Workspace Owner, I want to be stopped from leaving the Workspace until I transfer ownership, so that it never ends up without an owner.
47. As a Workspace Owner, I want to be stopped from deleting a Workspace that still holds Diagrams, so that hidden Diagrams are never exposed or deleted by accident.
48. As a User, I want to see which Workspaces I'm a Member of, so that I know where my Diagrams can go.
49. As a Diagram Owner, I want to put my Diagram into a Workspace I'm a Member of, so that the whole team can edit it without individual invitations.
50. As a Diagram Owner or Workspace Owner, I want to take a Diagram out of a Workspace, so that access goes back to being controlled only by Collaborators and the Diagram becomes visible to everyone again.
51. As a Member, I want to open the Editor and save the Workspace's Diagrams as a Collaborator would, so that we work together without asking for access.
52. As a Member, I want the Editing Lock to keep applying to Workspace Diagrams, so that there's no concurrent editing.
53. As a Member of a Workspace, I want its Diagrams hidden from Users who are neither Members nor have direct access, so that the team has a private space.
54. As a Collaborator of a Diagram inside a Workspace I'm not a Member of, I want to keep seeing and editing it, so that direct access keeps working.
55. As an Administrator, I want to see every Diagram, including hidden ones, so that I can still grant access and transfer ownership.
56. As a User removed from a Workspace, I want to lose access to its Diagrams unless I'm their Diagram Owner or a direct Collaborator, so that access reflects the current team.
57. As a User who forks a Diagram, I want to choose which of my Workspaces the Fork is created in (or none), so that the copy lands where it belongs.
58. As a User, I want to group and filter the Diagram list by Workspace, so that I can find my team's Diagrams.
59. As an Administrator, I want to link a Workspace to a Keycloak group, so that its Members mirror the group without keeping the same list in two places.
60. As a Workspace Owner, I want Members I added manually to never be removed by the Keycloak mirror, so that the automation doesn't undo my decisions.
61. As a User who left a linked Keycloak group, I want to lose that Workspace membership on my next Keycloak login, so that hidden Diagrams don't stay reachable after I leave the team.

**Named and comparable Versions**

62. As someone who can edit a Diagram, I want every Version to have a name, "Version N" by default and numbered sequentially, so that I can refer to Versions unambiguously.
63. As someone who can edit a Diagram, I want to rename a Version (e.g. "Approved by the board"), so that I can mark milestones.
64. As someone who can edit a Diagram, I want renamed Versions highlighted in the history, so that I can find milestones without reading dates.
65. As someone who can edit a Diagram, I want restoring an old Version to create a new, next-numbered Version, so that numbering never goes back.
66. As someone who can edit a Diagram, I want to compare an old Version with the current one on the canvas, with added, removed and changed elements highlighted, so that I understand what changed.
67. As someone who can edit a Diagram, I want to compare any two Versions, so that I understand the evolution between two milestones.
68. As a Diagram Owner, I want to create a Share pinned to a specific Version, so that recipients always see that Version even as the Diagram keeps changing.
69. As a Diagram Owner, I want to create a Share that always follows the latest Version, so that recipients always see the current state (the Phase 1 behaviour).

**Search and organization**

70. As a User, I want to search the Diagram list by title or Slug, so that I find what I'm looking for quickly.
71. As a User, I want to filter the list by "I'm the Diagram Owner", "I'm a Collaborator", "locked right now" and "recently modified", so that I can focus on what matters.
72. As a User, I want the list paginated and sortable, so that it stays fast with many Diagrams.
73. As someone who can edit a Diagram, I want to apply and remove Tags, so that I can organize Diagrams by subject or area.
74. As a User, I want to filter the list by Tag, so that I see every Diagram on a subject.
75. As a User, I want to search by BPMN content (task, lane and pool names), so that I find the process where an activity appears.
76. As a User, I want search and filters to only ever return Diagrams I'm allowed to see, so that hidden Diagrams never leak through search.

## Implementation Decisions

**Operational robustness**
- The existing disk/database compensation in the Diagram repository stays. This phase only adds failure-injection tests and fixes whatever they reveal.
- The app health check verifies PHP-FPM and the database connection. The web service depends on app being healthy.
- Backup and restore are Artisan commands. The backup is scheduled by Laravel's scheduler, which runs in its own compose service rather than a host cron. Each run produces one artifact containing the Diagrams directory (with the internal Version repositories) and a Postgres dump. Destination and retention are set by environment variables; the default is a host directory mounted as a volume, with 14 days of retention.
- Restore is an Artisan command that requires explicit confirmation, and it's documented in the README.
- Trusted proxies are configured so that generated URLs, including signed Share links, respect the `https` scheme forwarded by the external proxy.

**UX and visual design (ADR-0007)**
- Tailwind CSS 4 + Vite, already scaffolded. The Dockerfile gains a Node build stage. Assets are built at image build time and served by nginx; there's no CDN.
- A shared Blade base layout (header, navigation, notification bell) is the prefactor for every screen and comes first. The legacy stylesheet is removed at the end of the redesign.
- The Version history becomes a panel inside the Editor, consuming the existing Version endpoints.
- The Lock screen's 30-second countdown is derived from the existing lock-request endpoint. If that endpoint doesn't expose when the request was made, it starts doing so, as an additive contract change.

**Diagram Owner (ADR-0006)**
- The Diagram gains a Diagram Owner reference, backfilled from the Author for existing Diagrams. The Author reference stays and never changes.
- Every Phase 1 permission that checks "is the Author" switches to "is the Diagram Owner": delete, manage Collaborators, approve/refuse requests, manage Shares. The access policy's abilities keep their split: managing Collaborators is Diagram Owner or Administrator; managing Shares is Diagram Owner only.
- Transfer endpoint: callable by the current Diagram Owner or an Administrator. It takes the recipient and a "give up my access" flag. Unless that flag is set, the previous Diagram Owner becomes a Collaborator in the same transaction. No acceptance step; the recipient is notified.
- A Fork's Author and Diagram Owner are both whoever forked it.

**Notifications**
- Laravel's native Notifications, with the database channel as the in-app channel. Email is a second channel, enabled per User by a preference that is off by default.
- Events:
  - access request → Diagram Owner;
  - approval/refusal → requester;
  - invited/removed as Collaborator → that User;
  - Share about to expire → Diagram Owner;
  - became Diagram Owner / Workspace Owner → recipient;
  - added to / removed from a Workspace → that User.
- The Share-expiry warning is sent by a scheduled command. The lead time is configurable (default 2 days before `expires_at`), and the command never warns twice about the same Share.
- Email goes through a queue, using the already-configured database driver with a worker service in compose. The mailer is configured by environment variable; development keeps `log`.
- Endpoints: unread count (polled by the bell), paginated history (read and unread), mark one as read, mark all as read. There's no real-time push.
- The Editing Lock's request notice keeps its own polling mechanism and doesn't go through Notifications.

**Workspaces (ADR-0006)**
- New entities: Workspace (name, Workspace Owner reference, optional linked Keycloak group) and Membership (Workspace, User, origin: manual or group). A Diagram gains an optional Workspace reference, so it belongs to at most one Workspace.
- Any User creates a Workspace and becomes its Workspace Owner. The Workspace Owner adds and removes Members, renames, deletes, and transfers ownership. An Administrator can do all of that on any Workspace and is the only one who can link a Workspace to a Keycloak group.
- Deleting a Workspace is refused while it holds Diagrams. The Workspace Owner can't leave without transferring first.
- The Diagram Owner puts a Diagram into a Workspace they're a Member of. The Diagram Owner or the Workspace Owner takes it out.
- **Access policy:**
  - Edit (open the Editor, save, rename Versions, apply Tags): Diagram Owner, Collaborator, or Member of the Diagram's Workspace.
  - See (list, open, fork): the above, or an Administrator, or any User when the Diagram is in no Workspace.
  - The same visibility rule applies to the list, search, filters, and direct URL access. A hidden Diagram answers 404 to Users who can't see it, so its existence doesn't leak.
- The Phase 1 "request Collaborator access" action is only offered on visible Diagrams.
- Fork: the fork form offers the forker's Workspaces plus "none". The Fork lands in the chosen one.
- **Keycloak mirroring:** on each Keycloak login, group-origin memberships are reconciled against the User's groups. Missing ones are added, and those whose group is gone are removed. Manual memberships are never touched.

**Named and comparable Versions**
- Version numbers are derived sequentially per Diagram, in chronological order; existing Versions are numbered retroactively. Restore produces N+1, and a Fork starts at 1.
- Custom names are stored in the database, keyed by Diagram and internal Version identifier. No Git tags are used (ADR-0004 keeps Git invisible). A missing custom name displays as "Version N". Names needn't be unique.
- Comparison runs in the front-end. A new endpoint returns the BPMN content of a given Version, and the visual diff is computed and highlighted on the canvas with a BPMN diff library (e.g. `bpmn-js-differ`), vendored like bpmn-js.
- A Share gains an optional pinned Version. When it's absent, the Public Viewer serves the latest Version (the Phase 1 behaviour); when present, it serves that Version's content.

**Search and organization**
- Title/Slug search and filters are query parameters on the existing list, with server-side pagination, always composed with the visibility rule.
- Tags: a Tag entity (name, globally unique) with a many-to-many relation to Diagrams. Anyone who can edit a Diagram applies or removes its Tags, and Tags are created implicitly on first use.
- Content search: on save and on restore, the searchable text of the BPMN (names of tasks, events, gateways, lanes and pools) is extracted into a column indexed with Postgres full-text search. There's no external search engine.

## Testing Decisions

- **Main seam, same as Phase 1:** Feature tests through Laravel's HTTP test client with `actingAs()`, asserting on HTTP responses, database state and disk content. This covers failure consistency, Diagram Owner and its transfer, the visibility and edit policy (including 404 for hidden Diagrams and search never leaking them), Workspaces and membership, Keycloak mirroring (on top of the simulated Keycloak login from Phase 1), notifications (recipients and content; `Notification::fake` only for the email channel), Version names and numbering, the Version content endpoint, pinned and latest Shares, search, filters, pagination and Tags.
- **Console seam:** backup, restore and the Share-expiry warning are tested as Artisan commands (`$this->artisan(...)`), asserting on the produced artifact, the restored state, and the notifications created. This is the only new seam, and it sits at the highest point available for commands.
- **Failure injection:** disk failure is simulated through the faked/mocked storage disk, and database failure through an exception inside the transaction. Tests never call the Diagram repository's internals directly.
- **Time:** the expiry warning and its idempotence use `travelTo`, never `sleep`.
- **No front-end seam:** the UX tickets, the canvas diff, the health check and HTTPS behind the proxy are verified manually against the real Docker stack, as in Phase 1's ticket 09. No Dusk and no JS tests in this phase.
- **What makes a good test:** it tests observable behaviour (what the route or command returns, what gets persisted), never the implementation (no inspecting internal Git, no asserting on queries).
- **Prior art:** the Phase 1 Collaboration, Share, Diagram, Editing Lock and Version Feature tests, and the simulated Keycloak login tests.

## Out of Scope

- **Migrating legacy data** (`legacy/`): still out, as in Phase 1.
- **Merging a Fork back into its original:** still out.
- **Discoverable Workspaces and join requests:** a hidden Diagram is reachable only by invitation or membership.
- **Roles inside a Workspace** beyond the single Workspace Owner: every Member edits as a Collaborator would.
- **Invitation acceptance:** direct Collaborator invitations still grant access immediately, and ownership transfers need no acceptance.
- **Real-time notifications** (WebSocket/push): the bell polls.
- **Folders and hierarchy:** organization is by Workspace and Tag only.
- **Approval workflows on Versions:** a Version name is free text with no semantics.
- **Automated front-end tests.**
- **Managed off-host backup** (S3, an organization backup service): the destination is configurable, but this phase only implements a host directory.

## Further Notes

- Decisions were settled in a grilling session on 2026-09-24. The glossary (`CONTEXT.md`), the PT→EN mapping (`docs/GLOSSARY.md`) and ADR-0006/0007 were updated in the same session.
- Three fronts touch the Diagram list: the list redesign, search/filters, and grouping by Workspace. Search and grouping depend on the redesign, to avoid rework.
- The Diagram Owner prefactor comes before Notifications and Workspaces, because both refer to the Diagram Owner.
- The ticket breakdown is published as GitHub Issues on `solis-eduardo/bpmn-canvas` via `/to-tickets` once approved.
