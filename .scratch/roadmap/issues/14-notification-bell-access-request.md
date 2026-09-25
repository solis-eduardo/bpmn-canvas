# 14: In-app notification for access requests (bell, count, mark as read)

**What to build:** The notification system is born end to end: when someone requests Collaborator access, the Diagram Owner sees it under a bell with an unread count on every screen, clicks through to the Diagram's Collaborators screen, and can mark it (or all) as read.

**Blocked by:** 12 (Diagram Owner takes over the Author's powers)

**Status:** ready-for-agent

- [ ] Requesting access creates an in-app notification for the Diagram Owner.
- [ ] The base layout shows a bell with an unread count, refreshed by polling.
- [ ] Clicking a notification opens the relevant screen and marks it as read; "mark all as read" works.
- [ ] Feature tests: the right recipient gets it, the count endpoint and both mark-as-read actions behave, and other Users can't read someone else's notifications.
