# Server-side export via headless bpmn-js

Status: accepted

PNG and SVG export happened only in the browser (Editor and Public Viewer), and a Share's `allow_download` could only hand out the `.bpmn` (issue #250). A Share could not offer direct image links, there was no PDF at all, and nothing could embed a rendered Diagram later (emails, notifications).

**Decision:** render on the server with bpmn-js itself, so the output matches the Editor. bpmn-js needs a DOM and real text measurement, so `docker/renderer/render.mjs` runs it inside headless Chromium (`puppeteer-core`), as a Node subprocess started by PHP (`NodeDiagramRenderer`, behind the `DiagramRenderer` port so tests use a fake). The request goes in as JSON on stdin, the bytes come out on stdout, with a timeout. The SVG is produced in one page; PNG and PDF are drawn from it on a second page with JavaScript disabled, the Diagram embedded as an `<img>`, and every network request is refused, so nothing in a Diagram can execute or fetch. Chromium runs with `--no-sandbox` because the container has no user namespaces; the isolation above is what contains the page.

- A Share with `allow_download` serves `png`, `svg`, `pdf` next to `bpmn` at `/s/{token}/download/{format}`; the Public Viewer links to them directly instead of exporting in the browser. A pinned Share exports its Version.
- Signed-in Users who can open a Diagram export the same formats at `/diagrams/{slug}/export/{format}` (optionally `?version=`); the Editor offers the PDF.
- The PDF has a title block: title, Version (name, plus number when renamed), Diagram Owner, date. Labels are translated by PHP.
- Both routes are throttled (30 per minute per client): rendering starts a browser, and the Share route is public.
- A renderer failure answers 503 and is reported.

**Consequences:**
- The app image now ships Node, Chromium and a metric-compatible font (`font-liberation`), and its memory limit went up. The `queue` and `scheduler` services share the image but never render.
- Each export costs a browser start (about a second); nothing is cached.
- The standalone build keeps its in-browser PNG/SVG export; it has no server.

**Alternative not implemented:** a separate renderer service (HTTP) keeping Chromium out of the app image. It isolates memory and image size better but adds a service to deploy and secure; revisit if export volume grows.
