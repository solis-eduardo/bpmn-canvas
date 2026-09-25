# 08: Redesigned Editing Lock screen

**What to build:** Someone who runs into an Editing Lock sees who holds it and since when, can request it and watch a 30-second countdown until the transfer, or fork right there. The holder sees clearly in the Editor that someone requested the Lock.

**Blocked by:** 06 (Tailwind + Vite pipeline and shared base layout on the Diagram list)

**Status:** ready-for-agent

- [ ] The blocked screen shows the holder and when the Lock was taken.
- [ ] Requesting the Lock shows a countdown derived from the server's request time. If the lock-request response doesn't expose that time, it does now (additive change, with a Feature test).
- [ ] The Fork option is on the same screen.
- [ ] The Lock holder sees a visible notice in the Editor when a request is pending.
- [ ] Verified manually with two Users on the Docker stack.
