# Version store: a port with a Git adapter and a database adapter

ADR-0004 keeps Version history in a Git repository per Diagram, shelled out through `Symfony\Component\Process`. That cannot run where PHP has no subprocesses, such as the browser demo built on php-wasm (#209): there is no `git` binary, and the demo could not save, list, restore or compare Versions.

`VersionRepository` is now an interface (the port) that callers depend on, with two adapters:

- `GitVersionRepository`: the existing implementation. Its storage steps are unchanged, but writes to a Diagram's history are now serialized (see "Serialized writes" below). It is the default and the production store (ADR-0004).
- `DatabaseVersionRepository`: one row per Version in the `diagram_versions` table, holding the full content it recorded, with no subprocesses. It is written against the query builder and is verified on SQLite (the test database); PostgreSQL is not exercised by the suite.

The store is chosen by configuration: `VERSION_STORE=git|database` (`config/bpmn.php`, key `version_store`), defaulting to `git`. Behaviour shared by both adapters (Version state flags and names, history numbering, restore, post-save bookkeeping) lives in the `SharedVersionRepository` trait, so the adapters only differ in how they store and read Versions.

Both adapters must pass the same contract suite, `VersionRepositoryContract`, which exercises every interface operation: initialize, save, history order and numbering, get, exists, getMany, restore, rename, provisional and hidden Versions, and isolation between Diagrams. The database adapter also has a test asserting that it spawns no subprocess.

## Serialized writes (issue #242)

Two `git add`/`git commit` runs on one repository fight over `.git/index.lock`, and `save()` writes the file before committing, so an unserialized second writer could leave content on disk that never became a Version. `SharedVersionRepository::save()` therefore holds a per-Diagram cache lock, `diagram-versions:{id}`, around the file write and the commit. It applies to both adapters, since it lives in the trait, and the cache store is shared by the web app and the scheduler (`locks:expire`), so the lock holds across processes. The lock lapses by itself after 120 seconds, so a writer that dies mid-save cannot block the Diagram.

A writer waits up to `bpmn.version_lock_wait_seconds` (env `VERSION_LOCK_WAIT_SECONDS`, default 30). If the lock is still taken, `save()` writes nothing and throws `VersionHistoryBusyException` (it wraps the cache's `LockTimeoutException`). It is not a `RuntimeException`, so the controllers' "unknown Version" handling does not turn it into a 404. It is also not the `LockUnavailableException` of the editing Lock: that one is a 423, which the Editor reads as "you lost the Lock", while this is transient and retryable. Each caller handles it:

- **HTTP requests** (content save, restore, creating a Diagram, Fork, releasing a Lock with a draft, deactivating a User): the exception renders itself as `503` with `Retry-After: 5` and a translated message (`messages.error_history_busy`; JSON for the Editor, plain text otherwise). The Editor shows the message as a failed save and keeps the unsaved changes, so the User can save again. A release or deactivation runs in a transaction, so it is rolled back whole: the Lock and the draft stay as they were.
- **`locks:expire`**: `LockExpiryService` catches it per Diagram, logs a warning and carries on with the other Diagrams. That Diagram's Lock and draft are untouched (the release is rolled back) and the next run, a minute later, retries it.
- **`standalone:render`** creates its placeholder Diagram, whose lock nobody else can hold, and lets the exception end the command.
- There is no queue job that writes Versions.

Renaming, promoting and deleting a Version (`setState()`) only write the state table, not the history, and take no lock, so they never time out.

Still unlocked: renaming or moving a Diagram's Git directory (`DiagramRepository::renameWithin()`, `moveDirectory()`) runs outside this lock, so it can still race with a save. Whether to lock it is undecided.

## Consequences

- The default store is git, as before, and the existing tests pass against it, with one call site updated (`VersionRepository::defaultName()` moved to `App\Support\VersionName::default()`). The one behavioural difference is the serialized write described above: a save can now be answered with a 503 when the history stays busy.
- The database adapter stores full content per Version, the trade-off ADR-0004 rejected for storage efficiency. It exists for the browser demo; it is not a production store.
- Backups depend on the store. Git history lives in the diagrams directory, so it is in the archive. Database history lives in the database, so a restore depends on the database dump (`pg_dump`) being restored with it.
- The database adapter accepts only full 40-character Version ids, which is what `list()` returns. Git also resolves abbreviated commit ids.
- The `diagram_versions` table is created in every environment, even when the git store is in use, where it stays empty.
- The php-wasm spike stub (`spikes/php-wasm/overlay`) extends `VersionRepository`, which is now an interface, so it no longer loads. The spike should bind `VERSION_STORE=database` instead of the stub; it is left unchanged here.
