# Versioning Diagrams using Git internally, without exposing Git to the user

The legacy stack only kept the current version of the `.bpmn` file — overwritten on every save, with no history. The legacy README itself suggested, as a manual stopgap, versioning the `diagrams/` folder with Git via an external cron job.

We formalized that idea as part of the product: every save of a Diagram generates a Version internally, implemented as a Git commit on the file on disk (retaining everything, never discarding intermediate saves). To the user, history is exposed grouped by day — with no Git vocabulary or mechanics visible — and an old Version can be restored, which creates a new Version on top of the history with that content (revert-style, never discarding what came after).

We chose Git as the storage engine (instead of a bespoke versions table in the database, storing the full XML per row) because it's already efficient for incremental text snapshots, and because the legacy stack itself had already pointed in this direction as a backup strategy — just now automated and integrated into the product experience, instead of an external cron job.
