# 06: Tailwind + Vite pipeline and shared base layout on the Diagram list

**What to build:** The first redesigned screen goes live: the Diagram list renders inside a shared base layout (header, navigation, space for the notification bell) styled with Tailwind, built by Vite at image build time (ADR-0007).

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] The Dockerfile builds the Tailwind/Vite assets in a Node stage; runtime serves the built assets through nginx with no CDN.
- [ ] A shared Blade base layout exists and the Diagram list uses it; the other screens keep working unchanged.
- [ ] `npm run build` and the Docker image build both succeed; the Diagram list is verified manually on the Docker stack.
- [ ] The existing Feature tests stay green.
