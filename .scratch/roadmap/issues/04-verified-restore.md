# 04: Documented, verified restore

**What to build:** An operator can restore a backup artifact on a clean environment and get back working Diagrams, Versions, Collaborators and Shares.

**Blocked by:** 03 (Automated backup of Diagrams and the database)

**Status:** ready-for-agent

- [ ] An Artisan restore command takes an artifact and requires explicit confirmation before overwriting anything.
- [ ] After a restore, Diagrams open, their Version history lists and restores, and existing Share links work.
- [ ] Console-seam Feature test: back up, change state, restore, and assert the original state is back through the HTTP endpoints.
- [ ] The README gains a backup/restore section, and the procedure has been run once on a clean stack.
