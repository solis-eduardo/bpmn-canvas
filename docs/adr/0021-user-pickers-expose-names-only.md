# User pickers expose names only, never emails

Status: accepted

The endpoints that target a User by email (adding a Collaborator or Member, transferring ownership; issue #78) answer generically, so an authenticated User can't use them to probe which emails have accounts. Meanwhile the user pickers (new Diagram form, Workspace screens, Diagram list filters) were rendered with every User's name **and email**, which handed over the very list the generic answers hide and made the guard moot (issue #233).

**Decision:** treat the email as private to the account and its Administrators. Picker options carry only `id` and `name` (`Controller::userOptions`), and the picker is searched by name only. The email-targeted endpoints keep their generic answers.

**Trade-offs accepted:**
- A picker can no longer be searched by email.
- Two Users with the same name are indistinguishable in a picker. This is tolerated for an internal tool; if it becomes a real problem, the fix is to disambiguate with something that is not the email (for example a username or Workspace), not to bring the email back.

**Rejected alternative:** replace the pickers with a search endpoint returning a few matches as name plus masked email, on demand. It would keep email search and disambiguate same-name Users, but a masked email still leaks part of the address, an on-demand search endpoint can itself be scripted to enumerate the directory (so it would need rate limiting and a minimum query length), and it is a new endpoint and UI to build and maintain. Names alone give up less than that costs. Making the whole directory visible and dropping the generic answers was also rejected: it would turn the email into public information inside the tool.

The names themselves stay visible to every signed-in User; hiding them was never a goal.
