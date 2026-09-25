# 11: Redesigned Public Viewer and login

**What to build:** Share recipients get a clean Public Viewer showing the Diagram name, with export buttons only when the Share allows download; the login screen makes local and Keycloak login clearly distinct.

**Blocked by:** 06 (Tailwind + Vite pipeline and shared base layout on the Diagram list)

**Status:** ready-for-agent

- [ ] The Public Viewer shows the Diagram name and hides the export buttons when download isn't allowed.
- [ ] The login screen shows the Keycloak option only when it's configured, clearly separated from local login.
- [ ] After this ticket no screen depends on the legacy stylesheet, and the stylesheet is removed.
- [ ] Verified manually on the Docker stack; existing Share and Auth Feature tests stay green.
