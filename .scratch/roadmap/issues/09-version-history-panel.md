# 09: Version history as a panel in the Editor

**What to build:** Anyone editing a Diagram can open the Version history as a side panel inside the Editor, grouped by day, and restore a Version without leaving the Diagram.

**Blocked by:** 06 (Tailwind + Vite pipeline and shared base layout on the Diagram list), 18 (Editor gets the shared header and a way back to the list)

**Status:** ready-for-agent

- [ ] A panel in the Editor lists Versions grouped by day, using the existing Version endpoints.
- [ ] Restoring from the panel reloads the canvas with the restored content and shows the new Version at the top.
- [ ] Opened from the entry point the Editor's header reserves for it (ticket 18).
- [ ] Verified manually on the Docker stack; existing Version Feature tests stay green.
