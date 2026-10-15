# Optional off-site copy and a scheduled restore verification of backup artifacts

Status: accepted

ADR-0008's `backup:run` leaves its artifact on the same host and volume as the application, so a disk or host loss takes the backups with it, and a restore was only ever exercised by the test suite with a fake dumper. An untested backup is a hope, not a backup.

**Each artifact can be copied to an off-site disk.** With `BACKUP_OFFSITE_ENABLED`, `backup:run` streams the finished artifact to the `backups_offsite` disk (S3-compatible, its own credentials, swappable through `BACKUP_OFFSITE_DISK`) and prunes that disk by `BACKUP_OFFSITE_RETENTION_DAYS` (default 60), independent of the local `BACKUP_RETENTION_DAYS`. The local artifact is kept whatever happens; a failed upload fails the command and is recorded. We rejected syncing the `backups` volume with an external tool (rclone, a bucket replication rule) because, like the cron entry ADR-0008 rejected, it would live outside the repo and the Compose stack, and the app could not report whether it worked.

**A scheduled `backup:verify` restores the latest artifact into throwaway storage.** It extracts the newest artifact, validates the manifest, restores `database.dump` into a freshly created uniquely named Postgres database (dropped afterwards) and checks that every table is present and that no table which had rows at backup time came back empty, then runs `git fsck` on each Diagram's Version repository. The manifest therefore also records per-table row counts (taken just before the dump, transient tables such as sessions and cache excluded); the format version stays 1 since it only gains a key. Counts are compared as "had rows, still has rows" rather than for equality, because the live database keeps changing between the count and the dump. It never touches the application's own database or Diagrams disk, unlike `backup:restore`. It runs daily at 03:15, an hour after `backup:run`.

**Failures are recorded, logged and pushed to Administrators; status is visible to them.** Each outcome of `backup:run`, the off-site upload and `backup:verify` is a `backup_runs` row. A failed verification notifies every Administrator (database, plus email when they opted in) and writes an error log line. `/admin/backups`, reachable by Administrators only, shows when each last succeeded and the latest failure not yet followed by a success. Outcomes live in the database rather than on the backups volume because the web container does not mount that volume.

## Consequences

- The role in `DB_USERNAME` must be allowed to `CREATE DATABASE` for verification to work; otherwise `backup:verify` fails loudly, which is the intended behavior.
- Verification checks structure and non-emptiness, not row-for-row equality; a partially truncated table is not detected.
- A real `backup:restore` rolls `backup_runs` back with the rest of the database.
