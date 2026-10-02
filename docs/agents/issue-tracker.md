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

## Claiming an issue before implementing

Parallel sessions (human or agent) can pick up the same open issue at the same time — an issue's open/closed state and the PR list don't show an in-flight claim from another session started the same day. Before starting implementation (or dispatching an agent to do so):

1. Check the issue for an existing assignee or a recent "starting implementation" comment. If present, treat it as claimed — skip it or confirm with the user before proceeding.
2. Once clear, claim it: `gh issue edit <number> --add-assignee @me`, then post a comment announcing work is starting (e.g. "Reserved for implementation").
3. Do this *before* the implementation work begins, not after it finishes — the reservation is only useful if another session can see it in time.

## Full implementation workflow

### 1. Claiming the issue

- `gh issue edit <number> --add-assignee @me`
- Comment: "Reserved for implementation"
- Check `.scratch/<feature-slug>/spec.md` for specs and `docs/adr/` for context

### 2. Implementing on a feature branch

```bash
git checkout -b issue/NNN-slug
# ... implement the fix/feature
```

### 3. Review and refactor

- Run `/code-review` for correctness bugs
- Run `/simplify` for code quality improvements
- Commit fixes with clear messages

### 4. Push and create PR

```bash
git push -u origin issue/NNN-slug
gh pr create --title "..." --body "Fixes issue #NNN\n..."
```

Include "Fixes issue #NNN" in the PR body to auto-close the issue when merged.

### 5. After merge

The issue closes automatically via the PR commit message. If closed manually before merge, that's fine — the merged commit won't re-open it.

## Wayfinding operations

Used by `/wayfinder`. This is ephemeral research scaffolding for exploring a question, kept local — it does not move to GitHub Issues. The **map** is a file with one **child** file per ticket.

- **Map**: `.scratch/<effort>/map.md` (the Notes / Decisions-so-far / Fog body).
- **Child ticket**: `.scratch/<effort>/issues/NN-<slug>.md`, numbered from `01`, with the question in the body. A `Type:` line records the ticket type (`research`/`prototype`/`grilling`/`task`); a `Status:` line records `claimed`/`resolved`.
- **Blocking**: a `Blocked by: NN, NN` line near the top. A ticket is unblocked when every file it lists is `resolved`.
- **Frontier**: scan `.scratch/<effort>/issues/` for files that are open, unblocked, and unclaimed; first by number wins.
- **Claim**: set `Status: claimed` and save before any work.
- **Resolve**: append the answer under an `## Answer` heading, set `Status: resolved`, then append a context pointer (gist + link) to the map's Decisions-so-far in `map.md`.
