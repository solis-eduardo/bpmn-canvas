# 30: Compare a Version with the current one on the canvas

**What to build:** Anyone who can edit a Diagram opens a comparison of an old Version against the current content, with added, removed and changed elements highlighted on the canvas.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] A new endpoint returns the BPMN content of a given Version, and it respects the Diagram's edit permissions.
- [ ] A BPMN diff library is vendored like bpmn-js (no CDN) and highlights added, removed and changed elements.
- [ ] Feature test for the Version content endpoint; the canvas diff is verified manually.
