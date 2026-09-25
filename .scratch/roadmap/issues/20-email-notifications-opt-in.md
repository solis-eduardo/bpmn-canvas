# 20: Notifications by email, opt-in per User

**What to build:** Users can choose to also receive their notifications by email; it's off by default and can be turned off at any time.

**Blocked by:** 14 (In-app notification for access requests (bell, count, mark as read))

**Status:** ready-for-agent

- [ ] Each User has an email-notifications preference, off by default, editable from their account.
- [ ] When it's on, every notification is also sent by email through the queue; a queue worker runs as a compose service.
- [ ] The mailer is configured by environment variable; development keeps the `log` mailer.
- [ ] Feature tests with `Notification::fake`: email is sent only when the preference is on.
