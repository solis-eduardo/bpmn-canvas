# Issue tracker: GitHub Issues

Implementation issues for this repo are tracked as GitHub Issues on `solis-eduardo/bpmn-canvas`, not as markdown files.

Specs and planning docs still live under `.scratch/<feature-slug>/spec.md` — only the ticket breakdown moved to GitHub.

## Conventions

- One feature per directory for specs: `.scratch/<feature-slug>/spec.md`
- Implementation issues are GitHub Issues, one per ticket, titled `<feature-slug>: <ticket title>`
- Triage state is the GitHub Issue's open/closed status plus labels (see the repo's label list for the role strings)
- Comments and conversation history are GitHub Issue comments

## When a skill says "publish to the issue tracker"

Create a GitHub Issue with `gh issue create` (title + body from the ticket). If the feature doesn't have a spec yet, still create/update `.scratch/<feature-slug>/spec.md` first.

## When a skill says "fetch the relevant ticket"

Fetch the issue with `gh issue view <number>`. The user will normally pass the issue number or URL directly.

## Wayfinding operations

Used by `/wayfinder`. This is ephemeral research scaffolding for exploring a question, kept local — it does not move to GitHub Issues. The **map** is a file with one **child** file per ticket.

- **Map**: `.scratch/<effort>/map.md` (the Notes / Decisions-so-far / Fog body).
- **Child ticket**: `.scratch/<effort>/issues/NN-<slug>.md`, numbered from `01`, with the question in the body. A `Type:` line records the ticket type (`research`/`prototype`/`grilling`/`task`); a `Status:` line records `claimed`/`resolved`.
- **Blocking**: a `Blocked by: NN, NN` line near the top. A ticket is unblocked when every file it lists is `resolved`.
- **Frontier**: scan `.scratch/<effort>/issues/` for files that are open, unblocked, and unclaimed; first by number wins.
- **Claim**: set `Status: claimed` and save before any work.
- **Resolve**: append the answer under an `## Answer` heading, set `Status: resolved`, then append a context pointer (gist + link) to the map's Decisions-so-far in `map.md`.
