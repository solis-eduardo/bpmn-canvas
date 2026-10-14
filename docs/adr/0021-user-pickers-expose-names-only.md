# User pickers expose names only, never emails

Status: accepted

The endpoints that target a User by email (adding a Collaborator or Member, transferring ownership) answer generically, so they can't be used to probe which emails have accounts (issue #78). Meanwhile the user pickers (new Diagram form, Workspace screens, Workspace list filters) were rendered with every User's name **and email**, so any signed-in User could map an email to an account by reading the picker options. The guard was moot (issue #233).

**Decision:** keep the User directory visible to signed-in Users, but without emails. Picker options carry only `id` and `name`, the picker is searched by name only, and the email-targeted endpoints keep their generic answers.

**Consequences:**
- A picker can no longer be searched by email.
- Two Users with the same name are indistinguishable in a picker.

**Alternative not implemented:** replace the pickers with a search endpoint returning matches as name plus masked email (the issue's other option). It would restore email search and tell same-name Users apart. It was not part of the change that fixed the leak and remains an open option.
