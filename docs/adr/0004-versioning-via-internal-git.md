# Versioning Diagrams using Git internally, without exposing Git to the user

The legacy stack only kept the current version of the `.bpmn` file — overwritten on every save, with no history. The legacy README itself suggested, as a manual stopgap, versioning the `diagrams/` folder with Git via an external cron job.

We formalized that idea as part of the product: every save of a Diagram generates a Version internally, implemented as a Git commit on the file on disk (retaining everything, never discarding intermediate saves). To the user, history is exposed grouped by day — with no Git vocabulary or mechanics visible — and an old Version can be restored, which creates a new Version on top of the history with that content (revert-style, never discarding what came after).

We chose Git as the storage engine (instead of a bespoke versions table in the database, storing the full XML per row) because it's already efficient for incremental text snapshots, and because the legacy stack itself had already pointed in this direction as a backup strategy — just now automated and integrated into the product experience, instead of an external cron job.

## Amendment: provisional Versions and hiding

A Version left behind by an expiring Lock (ADR-0003) is a provisional Version, stored as an explicit flag (`is_provisional` on `diagram_version_names`) instead of being inferred from its localized name. An editor can make it permanent (clears the flag) or delete it.

Deleting does not bend "never discards intermediate Versions": it is a soft delete (`is_hidden`). The Git commit is kept and Version numbers, derived from position in the Git history, do not shift (dropping the commit would renumber every later Version and break Shares pinned to one). A hidden Version is only filtered out of the history list; Shares pinned to it and comparisons that reference it keep working. Only provisional Versions can be hidden, there is no automatic pruning, and any editor (`update` on the Diagram) may promote or delete.

## Amendment: the Version store is a port

`VersionRepository` is now an interface with a Git adapter (`GitVersionRepository`, still the default and the production store) and a database adapter for the browser demo, selected by `VERSION_STORE`. See ADR-0018.
