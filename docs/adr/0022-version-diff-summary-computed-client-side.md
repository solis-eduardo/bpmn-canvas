# The Version diff summary is computed client-side

Status: accepted

Comparing Versions already overlays the differences on the canvas. For larger Diagrams a list of what changed is easier to review (issue #249), so the comparison now also shows a summary panel: elements added, removed, renamed, moved and reconnected, by BPMN element id and type, each clickable to focus the element on the canvas.

**Decision:** compute the summary in the browser from the two BPMN XML documents, reusing the `bpmn-js-differ` result the comparison already produces (`public/assets/diff-summary.js`). Renamed means a changed `name`; reconnected means a changed `sourceRef` or `targetRef`; moved means a layout change (position, size or waypoints) of an element that exists on both sides.

**Consequences:**
- No new endpoint, storage or schema, and the same code serves the Laravel app and the standalone build (ADR-0019).
- The summary exists only while a comparison is open; it is not available to other consumers such as notifications.

**Alternative not implemented:** compute the summary server-side, which would let notifications say "Version 12: 3 tasks added". It would need a BPMN parser and differ on the PHP side, duplicating the front-end engine. It remains an open option if such notifications are wanted.
