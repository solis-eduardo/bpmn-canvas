# 18: Editor gets the shared header and a way back to the list

**What to build:** The Editor stops being an island: it shares the base layout's header and navigation, offers a clear way back to the Diagram list, and reserves the header space that the Version history panel (09) and Collaborators/Shares screen (10) will use.

**Blocked by:** 06 (Tailwind + Vite pipeline and shared base layout on the Diagram list)

**Status:** ready-for-agent

- [ ] The Editor uses the shared base layout's header (same nav, logout, notification-bell space as other screens).
- [ ] A visible control returns to the Diagram list without losing unsaved-changes protection.
- [ ] The header reserves entry points for Version history and Collaborators/Shares (wired up in tickets 09 and 10).
- [ ] Verified manually on the Docker stack; existing Editor Feature tests stay green.
