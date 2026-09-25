# 17: App entry points lead to the Diagram list

**What to build:** Whether landing on the root URL, logging in locally, or logging in via Keycloak, a User always ends up on the Diagram list — never on a dead end or the framework's default page.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] `/` redirects to the Diagram list when authenticated, or to `/login` when not; the Laravel default welcome page and its broken "Dashboard" link are gone.
- [ ] Local login and Keycloak login both redirect to the Diagram list on success (not `/home`).
- [ ] The dead-end `/home` screen is removed.
- [ ] Feature tests cover both redirect cases (authenticated and guest) and both login flows.
