# Spike #209: running the Laravel app in the browser via php-wasm

Status: **GO, with conditions** (see "Not verified in a browser"). Throwaway code; nothing here touches the app (`app/`, `routes/`, ...). The staged copy of the app is patched by `patch-stage.php` only.

## What was built

| File | Purpose |
|---|---|
| `build-stage.sh`, `patch-stage.php` | Builds a throwaway app copy: `composer install --no-dev`, `vite build`, vendored bpmn-js, `.env.demo`, SQLite migrated + seeded with `DemoSeeder`, database Version store, sub-path fix, CSP `frame-ancestors 'self'`. |
| `check-extensions.mjs` | PHP version and extensions of the php-wasm 8.4 build. |
| `node-flow.mjs` | Headless HTTP flow against the app inside php-wasm under Node (`BUNDLE=1` = unzip `bundle/app.zip` into the in-memory FS like the browser; `BASE=/bpmn-canvas/app` = Pages-style sub-path). |
| `build-bundle.sh`, `web/` | Static site: shell page (boots php-wasm, unzips the app, mounts OPFS, answers the Service Worker), `sw.js`, `app.zip`. Built, **never run in a browser**. |
| `serve.mjs` | Static server mimicking Pages (no COOP/COEP, sub-path, on-the-fly compression). |
| `measure-size.mjs` | First-load download size (raw/gzip/brotli). |

Reproduce: `npm ci && ./build-stage.sh && ./build-bundle.sh`, then `node check-extensions.mjs`, `BUNDLE=1 BASE=/bpmn-canvas/app node node-flow.mjs`, `node measure-size.mjs`.

## Questions

### 1. PHP 8.4 and extensions: YES (verified, Node build; same wasm family as the browser)
`@php-wasm/node` 3.1.58 runs PHP 8.4.25. Present: mbstring, openssl, pdo, pdo_sqlite, sqlite3, ctype, tokenizer, fileinfo, json, xml, dom, simplexml, xmlwriter, curl, filter, hash, iconv, session, zip, zlib, bcmath, gd, pcre, random, phar. Missing: `intl` (available as an optional lazily-fetched ~60 MB extension; the app does not need it, `composer.json` has no `ext-*`), `pcntl`, `posix`, `sodium`, `readline` (not needed). Laravel 13 booted with no code change.

### 2. Works without COOP/COEP: YES by design, **not verified in a browser**
- The shell runs php-wasm on the main thread and the code path used (`loadWebRuntime` + `PHPRequestHandler`) contains no `SharedArrayBuffer` use; the only SAB/`Atomics.wait` code in the bundle is a Node worker message path. Per web.dev, SharedArrayBuffer is what requires cross-origin isolation.
- `serve.mjs` served wasm/js/zip with no COOP/COEP headers (checked with curl), correct `application/wasm` type.
- Not checked: a real browser run. The runtime is `jspi` or `asyncify` (picked by feature detection); both builds ship (~19 MB each, only one is fetched).
- Risk: PHP on the main thread freezes the UI during requests (~150-450 ms each under Node). A Dedicated Worker hosting php-wasm (relayed by the shell) is the likely hardening; also unverified.

### 3. Persistence: design only, **not verified in a browser**
- Design: `createDirectoryHandleMountHandler` (OPFS) mounted on `/persist` (SQLite file, sessions in the DB) and on `storage/app/diagrams`; first run copies the seed memory-to-OPFS, later runs OPFS-to-memory; `flush()` after each non-GET request. Cookies live in a jar in `localStorage` because a Service Worker cannot set cookies and the session cookie is HttpOnly.
- Verified under Node only: the app state works against a mounted `/persist/db.sqlite` and MEMFS; write -> read round-trip OK. OPFS/IndexedDB survival across reload was not tested.
- Caveats: the data is per-browser and per-origin, lost on "clear site data"; flush is asynchronous (a tab closed right after a write may lose it).

### 4. Download size and load time
Measured (`measure-size.mjs`, one 8.4 variant, shipped files only):

| | raw | gzip -9 | brotli -11 |
|---|---|---|---|
| php wasm + loader | 19.2 MiB | 7.4 MiB | 5.3 MiB |
| `app.zip` (vendor, views, assets, seed DB) | 9.9 MiB | 8.2 MiB | 8.0 MiB |
| shell JS/HTML/SW | 0.2 MiB | ~0.1 MiB | ~0.1 MiB |
| **Total first load** | **29.3 MiB** | **15.7 MiB** | **13.4 MiB** |

- A deflate-compressed zip does not compress again. Storing the zip uncompressed (`zip -0`, 35.8 MB) and letting the host gzip it gives 6.3 MiB (saves ~2 MiB with gzip; not verified that Pages compresses `.zip`, so use a compressible extension or a pre-compressed format). Pruning `vendor/` (53 MB, no `--classmap-authoritative` or stripping done) can reduce this further.
- GitHub Pages enforces a 1 GB site limit and a 100 GB/month soft bandwidth limit (docs): not a constraint. Pages' encodings (gzip vs brotli) were not verified.
- Under Node (laptop CPU, not a mid-range one): runtime ready ~110 ms, unzip of the bundle ~0.8-1.0 s, first `GET /login` 300-460 ms, then 130-230 ms per request. So CPU-bound start is ~1.5-2 s plus download. **Browser time-to-interactive was not measured** (needs wasm compile in a browser, OPFS sync, iframe load, bpmn-js).
- The Service Worker and later visits can cache the wasm and zip (the shell should check Cache Storage), so only the first visit pays.

### 5. Other subprocess / network / service paths
Only `app/Support/ProcessRunner.php` (Symfony Process) spawns processes. Users: `VersionRepository` (git) and the Pg backup/restore (`PgDumpDatabaseDumper`, `PgRestoreDatabaseRestorer`, `BackupRepository`). Everything else to disable/replace in the demo:
- Version store: originally stubbed here; now `VERSION_STORE=database` (#210, merged). `proc_open` exists as a function in php-wasm Node but there is no git binary.
- Backups (`backup:run`, `backup:restore`, pg_dump/pg_restore): not applicable with SQLite; disable.
- Scheduler (`backup:run`, `shares:warn-expiring`, `locks:expire` every minute): nothing runs cron in the browser. Lock expiry needs a replacement (run lazily on request or on a timer in the shell).
- Keycloak/Socialite login: outbound OAuth redirect; disable, use local login (`/login` works).
- Mail/notifications: `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`; database notifications work. Real mail cannot be sent.
- Sessions use the `database` driver and cache the `file` driver; both work in php-wasm.
- CSP: the shell embeds the app in an iframe, so `frame-ancestors` was relaxed to `'self'` in the staged copy only.
- The CSP `connect-src 'self'` is fine since all requests go to the same origin through the Service Worker.

### 6. GitHub Pages viability for a private repo
- Pages works from private repos only on paid plans (GitHub Pro, Team, Enterprise Cloud) per GitHub docs; on Free the repo must be public.
- A published Pages site is public to the internet unless the organization is on **GitHub Enterprise Cloud** and chooses a private (access-controlled) site, where only people with read access to the repo can view it.
- The bundle contains the full application source (`vendor/`, `app/`, views, migrations) and the demo database, readable by anyone who can open the site. Acceptable only if the source may be public.
- Custom HTTP headers are not configurable on Pages (the issue's premise; the docs fetched are silent). This spike does not depend on them.
- **Needs a user decision** (plan/visibility); nothing was deployed and the repo was not made public.

## Verified end to end (Node, `BUNDLE=1 BASE=/bpmn-canvas/app`)
GET `/login` (CSRF token), POST `/login` (302 to `/home`, session cookie), `/home`, `/diagrams`, `/diagrams/create`, POST `/diagrams` (302), list shows the new Diagram, Editor workspace page, `GET .../content`, `PUT .../content` (save, `{"saved":true}`), read-back shows the edit. Generated URLs carry the `/bpmn-canvas/app` prefix (via an `X-App-Base` header turned into `SCRIPT_NAME` in the staged `public/index.php`). Static assets are served by the handler with the right content type. The same flow also passes with the staged tree mounted from disk.

Details found while building:
- Laravel 13's `PreventRequestForgery` accepts `Sec-Fetch-Site: same-origin`; browsers do not let page JS set it, so the Service Worker adds it (everything it forwards is same-origin).
- php-wasm request bodies must be `Uint8Array` (strings drop form fields).
- `@php-wasm/web` references every PHP version; `build-bundle.sh` deletes all non-8.4 wasm and the `intl`/ICU files (otherwise the bundle is ~160 MB).

## Not verified in a browser (hard constraint for this spike)
Service Worker registration/control and the shell <-> SW message relay (`web/src`, `web/public/sw.js` are written but never executed), the iframe load, OPFS mount/flush/reload persistence, COOP/COEP-free operation in a real browser (JSPI/asyncify selection), main-thread blocking, time to interactive, bpmn-js rendering in the Editor, behaviour of Safari/Firefox (OPFS, JSPI). These are the first things #212 must verify; consider a short follow-up manual check of the built `bundle/` with `node serve.mjs bundle 8081 /bpmn-canvas/` before investing further.

## Go / no-go
**GO** on the server side: the real app runs unchanged on PHP 8.4 in php-wasm, including login, CRUD, and Editor save. The browser layer is plausible but untested; the gating risks are Service Worker + OPFS behaviour and the Pages visibility decision.

## Adjustments to follow-ups
- **#210 Version store without Git (done, merged):** the seam is `VersionRepository` (bound in `AppServiceProvider`); the database adapter is bound there. Needed a non-git implementation (e.g. versions as rows/files in SQLite) honouring `initialize/save/list` and whatever `DiagramVersionService`/`ProvisionalVersionService` call; also decide how the Version diff views behave. Disable `pg_dump`-based backup in the demo.
- **#211 Demo env/seeder (done, merged; the spike stage now uses `.env.demo` and `DemoSeeder`):** SQLite (`DB_DATABASE=/persist/db.sqlite`; the shipped `.env.demo` settled on `SESSION_DRIVER=file`), `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`, `LOG_CHANNEL=stderr`, `APP_DEBUG=false`; seed users and sample Diagrams, with no Keycloak. Add a "reset demo data" action (clear OPFS + localStorage jar). Decide a lock-expiry replacement.
- **#212 Pages deploy:** build in CI (composer no-dev, vite, php-wasm bundle with only 8.4 and no intl), project-site sub-path via relative base plus `X-App-Base`, `404.html`/SW fallback, cache strategy for wasm/zip, size budget ~13-16 MiB first load. Blocked on the visibility/plan decision above. The `patch-stage.php` hacks (sub-path, CSP) should become proper config or build-time options.
