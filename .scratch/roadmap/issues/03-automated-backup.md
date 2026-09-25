# 03: Automated backup of Diagrams and the database

**What to build:** Operators get a periodic backup that captures every Diagram (including its Version history) and the database in the same window, written to a configurable destination with retention, so losing the host doesn't lose work.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] An Artisan backup command produces one artifact per run containing the Diagrams directory (with the internal Version repositories) and a Postgres dump.
- [ ] Laravel's scheduler runs the backup periodically from its own compose service (no host cron).
- [ ] Destination and retention are set by environment variables; the default is a host directory mounted as a volume with 14 days of retention. Older artifacts are pruned.
- [ ] Console-seam Feature test: running the command produces an artifact with both parts, and retention prunes expired artifacts (time travelled, not slept).
- [ ] The new environment variables are listed in `.env.example`.
