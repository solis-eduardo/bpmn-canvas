# 10: Redesigned Collaborators and Shares screens

**What to build:** Managing access becomes legible: one screen shows Collaborators and pending requests (invite, approve, refuse, remove), and active Shares with expiry and download permission, each with a one-click copy of the link.

**Blocked by:** 06 (Tailwind + Vite pipeline and shared base layout on the Diagram list), 18 (Editor gets the shared header and a way back to the list)

**Status:** ready-for-agent

- [ ] Collaborators, pending requests and their actions are on one clear screen.
- [ ] Active Shares list expiry and download permission, with copy-to-clipboard and revoke.
- [ ] Reachable from the entry point the Editor's header reserves for it (ticket 18).
- [ ] Verified manually on the Docker stack; existing Collaboration and Share Feature tests stay green.
