# Modeling checks via bpmnlint, bundled with Vite

Status: accepted

The Editor accepts any well-formed BPMN, so common modeling mistakes (disconnected elements, gateways that are entered but never left, missing start/end events, unlabeled tasks) go unnoticed. We want non-blocking checks in the Editor, with a toggle and an issue count in the header.

**Decision:** integrate `bpmnlint` through `bpmn-js-bpmnlint`, with bpmnlint's recommended rules plus one of ours (`gateway-outgoing-required`).

- Every rule reports as a warning. The checks never block editing or saving. `no-bpmndi` is left out: the Modeler draws from the diagram interchange, so a diagram that opens always has it.
- The rule set is packed by hand in `resources/js/editor-lint.js` (a resolver over bpmnlint's `rules/*.js`, found with `import.meta.glob`), so no bundler plugin is needed.
- The header toggle and count (`lint-ui.js`) belong to the shared core (ADR-0019). `createEditor` takes an optional `lint` ({modules, options}); without it the Editor has no checks and the toggle stays hidden. The User's last toggle choice is kept in `localStorage` (on by default); bpmn-js-bpmnlint's own canvas button is hidden in favour of the header's.
- The standalone build imports `editor-lint.js` directly. The server build runs the vendored, unbundled bpmn-js, so `editor-lint.js` is a Vite entry and `editor.js` loads its bundle with a dynamic `import()` of the URL the page gives (`data-lint-url`). If it fails to load, the Editor opens without checks.

**Why:** reuses the maintained bpmn.io linter and its overlays instead of writing our own, and keeps one rule set for both builds.

Not done: per-Workspace rule configuration (the issue's optional "later"). The rule set is fixed in code.
