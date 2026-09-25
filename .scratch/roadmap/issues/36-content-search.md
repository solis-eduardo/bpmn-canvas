# 36: Search by BPMN content

**What to build:** Users find the process where an activity appears by searching for the names of tasks, events, gateways, lanes and pools.

**Blocked by:** 33 (Search the Diagram list by title or Slug)

**Status:** ready-for-agent

- [ ] On save and on restore, the searchable text of the BPMN is extracted and indexed with Postgres full-text search; existing Diagrams are indexed once.
- [ ] The list search also matches that content, still respecting visibility.
- [ ] Feature tests: a task name finds its Diagram, the index follows saves and restores, and hidden Diagrams stay out.
