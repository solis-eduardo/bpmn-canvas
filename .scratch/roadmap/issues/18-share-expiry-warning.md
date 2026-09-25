# 18: Warning when a Share is about to expire

**What to build:** A Diagram Owner is warned before one of their Shares expires, so they can renew the link before recipients lose access.

**Blocked by:** 14 (In-app notification for access requests (bell, count, mark as read))

**Status:** ready-for-agent

- [ ] A scheduled Artisan command notifies the Diagram Owner of each active Share expiring within the configurable lead time (default 2 days).
- [ ] The same Share is never warned about twice; revoked or already-expired Shares aren't warned about.
- [ ] Console-seam Feature tests using `travelTo`: warns once, never twice, and skips revoked Shares.
