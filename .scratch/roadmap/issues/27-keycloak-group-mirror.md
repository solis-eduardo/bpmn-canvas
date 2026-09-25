# 27: Mirror Workspace Members from a Keycloak group

**What to build:** An Administrator links a Workspace to a Keycloak group, and its Members then follow the group on each Keycloak login, without ever touching manually added Members.

**Blocked by:** 21 (Create a Workspace and manage its Members)

**Status:** ready-for-agent

- [ ] Only an Administrator can link a Workspace to (or unlink it from) a Keycloak group.
- [ ] On each Keycloak login, the User's group-origin memberships are reconciled: missing ones are added and those whose group is gone are removed.
- [ ] Manual memberships are never added or removed by the mirror, even if the User also belongs to the group.
- [ ] Feature tests on top of the simulated Keycloak login: join, leave, and manual membership preserved.
