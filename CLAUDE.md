## Implementation workflow

### Before implementing

1. **Reserve the issue** on GitHub: assign to yourself + comment "Reservando para implementação"
2. Check `.scratch/<feature-slug>/` for specs if present
3. Review relevant `docs/adr/` decisions for context

### While implementing

1. Create a feature branch: `git checkout -b issue/NNN-slug`
2. Implement the fix/feature on that branch
3. Run `/code-review` and `/simplify` to catch issues
4. Commit with meaningful messages (include "Fixes issue #NNN" in body)
5. Push and open a PR against `main`

### After PR merge

Close the issue on GitHub when the PR is merged. If the issue was already closed, that's fine — the commit message closes it automatically when merged.

---

## Agent skills

### Issue tracker

Issues live as GitHub Issues on `solis-eduardo/bpmn-canvas`. Specs still live under `.scratch/<feature-slug>/`. See `docs/agents/issue-tracker.md`.

### Domain docs

Single-context layout: `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.
