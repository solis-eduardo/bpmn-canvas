# Append-only activity log for access changes

Status: accepted

Sensitive changes used to leave no trace other than the notification sent to the affected User (issue #252): ownership transfers, Collaborator grants, removals and role changes, Share creation and revocation, an Administrator's Lock force-release, Workspace membership changes and Administrator role changes. Nobody could answer "who gave this person access, and when?" after the fact.

**Decision:** every one of those changes writes one entry to an append-only activity log (`activity_logs`), through `ActivityLogger`, called from the service or controller that performs the change (so a change that is refused or rolled back is not logged). An entry holds the actor, the action, the Diagram and/or Workspace it concerns, the subject (the User, Workspace or Share acted on), a `before` and an `after` and the time.

**Rules:**
- *Append-only.* The model refuses updates and deletes. Entries leave only through retention.
- *Snapshots, no foreign keys.* Actor, Diagram, Workspace and subject names are copied into the entry and their ids carry no foreign key, so an entry stays readable and keeps its meaning after a rename, a deletion or a deactivation.
- *No actor for what the application does itself.* A change made by a Keycloak login (Administrator claim, group-managed memberships) has no actor and is shown as "System", even if another User happens to be signed in in the same browser.
- *No secrets.* A Share is identified by its id, never its token.
- *Only real changes.* Re-granting access someone already has, a transfer to the current Diagram Owner, or a holder releasing their own Lock log nothing. A self-service access request is not logged until it is approved or declined.
- *Visibility.* A Diagram's Activity panel, a tab of the Editor's side panels that loads its entries (JSON, newest first, paged, filterable) without leaving the Editor (and in the read-only Viewer, where an Administrator who isn't the Owner lands), is for those who can manage its Collaborators (Diagram Owner, Diagram Manager, Administrator); a hidden Diagram answers 404. The global log is for Administrators only and filters by action, actor, Diagram and date range.
- *Retention.* `ACTIVITY_LOG_RETENTION_DAYS` (`config/activity.php`), default 0 = keep forever. `activity:prune` runs daily and is the one place that deletes entries.

**Why:** a notification is addressed to the affected User, can be read and dismissed, and says nothing to the people who administer access. A log written at the point of change, with snapshots, answers the audit question without depending on current state.

**Consequences:**
- Rows removed by a cascade (deleting a Diagram drops its Collaborators and Shares, deleting a Workspace drops its Members) are not logged entry by entry.
- Changes made directly in the database, or by code paths that bypass the services and controllers above, are not logged.
- Entries record names as they were; a later rename does not rewrite history.
