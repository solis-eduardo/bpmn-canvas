# 20: New Diagram's start event is localized

**What to build:** A newly created Diagram's start event label matches the User's locale, instead of the hard-coded English "Start".

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] The seed BPMN XML's start-event label is generated per the current locale (e.g. "Início" in pt_BR) instead of a fixed "Start".
- [ ] Covered by a test creating a Diagram under a non-English locale.
