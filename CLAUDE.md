## Agent skills

### Issue tracker

Issues live as GitHub Issues on `solis-eduardo/bpmn-canvas`. See `docs/agents/issue-tracker.md`.

### Domain docs

Single-context layout: `CONTEXT.md` + `docs/adr/` at the repo root. See `docs/agents/domain.md`.

## Language of repository content

Everything that lives in or around the repository is written in **English (en-US)**, even though the chat with the user is in pt-BR. This covers:

- Commit messages (subject and body), including merge commits
- GitHub issue titles/bodies, PR titles/bodies, and every issue/PR comment (claim comments such as "Reserved for implementation", progress and "Implemented in commit …" notes, closing notes)
- Code comments, docblocks, docs (`CONTEXT.md`, `docs/`, ADRs), and branch names

The only exception is user-facing UI strings and test fixtures for the pt-BR locale (`lang/pt_BR`, assertions on translated text).

Before running `git commit`, `gh issue/pr create|edit|comment`, check the text you are about to publish is in English.
