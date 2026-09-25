# 19: Administrator manages User accounts from the interface

**What to build:** An Administrator can list Users, create a local account, and grant or revoke the Administrator role, all from a screen — today only the `POST`/`PUT` endpoints exist, with no page calling them, so accounts can only be managed by hand against the API.

**Blocked by:** 06 (Tailwind + Vite pipeline and shared base layout on the Diagram list)

**Status:** ready-for-agent

- [ ] A screen, reachable only by Administrators, lists Users with their login method and Administrator status.
- [ ] An Administrator can create a local account from this screen.
- [ ] An Administrator can grant or revoke the manual Administrator role from this screen; a role granted via Keycloak claim is shown but can't be revoked here (per the existing rule: only a manual grant can be turned off).
- [ ] Feature tests cover access denial for non-Administrators and the create/grant/revoke actions.
