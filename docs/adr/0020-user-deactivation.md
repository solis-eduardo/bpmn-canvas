# Deactivating a User account

Status: accepted

An Administrator can deactivate a User (issue #244). Deactivation is a state on the account (`deactivated_at`, null when active), not a deletion: the User's authorship and history stay in place, and reactivation restores access.

**Rules:** a deactivated User can't sign in by either login method. Their sessions and remember-me token are revoked (`UserSessionRevoker`), and a session that survives (file or cookie drivers, which can't be enumerated by User) is signed out on its next request. Their Locks are released through the normal release path, so unsaved content is kept as a provisional Version. They're hidden from pickers (the collaborator, owner and member selectors), but their existing Collaborator and Member rows are kept, so reactivation needs no re-invitation. Diagrams and Workspaces they own stay owned by them and are listed on the User's edit screen; an Administrator can transfer all of them to an active successor in one action (Diagram Owner transfers drop the previous owner's access entirely; Workspace transfers keep them as a Member). Administrators can't deactivate their own account; since the acting Administrator is always another active one, at least one Administrator always remains. Authorship is never touched (CONTEXT.md, "Author").

**Why:** deleting the account would orphan authorship, Versions and Notifications; a plain flag keeps the history and makes the action reversible. Ownership is handed over explicitly rather than automatically, because only a person can choose the right successor. Revoking sessions alone is not enough for every session driver, so the request-time check is the backstop.
