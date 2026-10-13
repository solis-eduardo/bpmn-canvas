# Shared Editor core behind a DiagramStore seam, with a standalone build

Status: accepted

We want a **standalone build**: a single HTML file that runs the Editor entirely in the browser (opened from disk or any static host), with no server, Docker, login or database. The server build stays as it is. Both builds must share the Editor's code, so a fix or feature in the Editor reaches both.

The business rules live in PHP (`app/`, ~8.7k lines) and the screens in Blade, neither of which runs in a browser. Most of them (Lock, Heartbeat, Collaborators, Diagram Managers, Workspaces, Shares, notifications, Keycloak) only make sense with several Users and a server, so the standalone build drops them rather than reimplementing them. What both builds genuinely share is the client: the bpmn-js Modeler, exports, the Version history panel and the comparison (`diff.js`). The only server coupling in that code is a handful of `fetch` calls.

**Decision:** the Editor's client code becomes a shared core that reaches persistence only through one seam, `DiagramStore`, with two adapters:

- `HttpDiagramStore` (server build): `fetch` to the existing Laravel endpoints, CSRF, `httpError`.
- `LocalDiagramStore` (standalone build): IndexedDB, one record per Diagram plus one snapshot per Version.

```js
/**
 * @typedef {Object} Version
 * @property {string}  id
 * @property {number}  number       sequential within the Diagram
 * @property {string}  name         "Version N" or a custom name
 * @property {string}  savedAt      ISO-8601
 * @property {?string} author       null in the standalone build
 * @property {boolean} provisional  always false in the standalone build
 * @property {boolean} current
 *
 * Rejects with an Error that may carry `serverMessage` (already localized).
 */
load()                    // → Promise<string>     current BPMN XML
save(xml)                 // → Promise<void>       creates a Version
versions()                // → Promise<Version[]>  flat, newest first, hidden Versions omitted
versionContent(id)        // → Promise<string>
restoreVersion(id)        // → Promise<void>       creates Version N+1 (revert-style)
renameVersion(id, name)   // → Promise<void>
promoteVersion(id)        // → Promise<void>       provisional → permanent
deleteVersion(id)         // → Promise<void>       soft delete, provisional only
```

**Rules:**
- The core (`createEditor({ container, store, t, timeZone })`, the Version history panel, `diff.js`, `common.js`) knows nothing about Locks, URLs or CSRF. It exposes `isDirty()`, `currentXml()` and `dirty`/`saved` events.
- Lock handling (Heartbeat, release, lock-request banner) moves out of the core into a server-only module built on those events and accessors. Collaborators, Shares, Diagram settings and notifications stay server-only.
- The interface has no capability flags. Actions are decided by the data, as today: no provisional Versions means no promote/delete actions, a null `author` hides the author line.
- `versions()` returns a flat list; grouping by day becomes a display concern of the core, using the `timeZone` the shell passes (the User's timezone on the server, the browser's in the standalone build).
- There is no `compare(from, to)` on the seam: the core fetches both contents with `versionContent` and orders them by `number`, as `DiagramVersionService::compare` did. The `/versions/compare` endpoint is then retired.
- The list of Diagrams (create, delete, import/export `.bpmn`) is not part of `DiagramStore`: only the standalone build needs it, so it lives in a standalone-only library module. Saving to a chosen file uses the File System Access API where available (Chromium), falling back to upload/download.
- UI strings stay sourced from `lang/`; a build step exports the keys the core needs to JSON, consumed through `t(key)` by both builds instead of `data-*` attributes.
- The standalone build lives in this repo (`standalone/`) and is bundled by Vite into one self-contained HTML file, since ES modules and import maps don't load over `file://`.
- Title and Tags editing are out of the standalone build's first iteration, to keep the seam to the Editor's needs.

**Why:** a shared core with a persistence seam centralizes everything the two builds really have in common while keeping the server's domain rules in PHP, where they're enforced. The duplicated domain logic is small: `LocalDiagramStore` re-implements only Version numbering, the default "Version N" name, restore as N+1 and soft delete. Two real adapters also make the seam testable with one contract suite run against both (`LocalDiagramStore` with `fake-indexeddb`, `HttpDiagramStore` with a stubbed `fetch`).

Rejected alternatives:
- **Running Laravel in the browser via PHP WebAssembly:** total code sharing, but tens of MB to download, no `git` binary for Versions (ADR-0004), and the queue and scheduler would have to be emulated.
- **Moving the domain to TypeScript, with Laravel as API and persistence only:** centralizes everything but amounts to rewriting the backend.
- **A desktop wrapper (Electron/Tauri/NativePHP) around the full app:** carries the whole multi-User domain into a single-User context where most of it is dead weight.

Standalone data lives in the browser profile and is lost if the User clears site data; the standalone build offers an "export all" backup for that reason. Versions there are snapshots, not Git commits: ADR-0004 still governs the server build.
