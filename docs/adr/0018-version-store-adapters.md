# Version store: a port with a Git adapter and a database adapter

ADR-0004 keeps Version history in a Git repository per Diagram, shelled out through `Symfony\Component\Process`. That cannot run where PHP has no subprocesses, such as the browser demo built on php-wasm (#209): there is no `git` binary, and the demo could not save, list, restore or compare Versions.

`VersionRepository` is now an interface (the port) that callers depend on, with two adapters:

- `GitVersionRepository`: the existing implementation, unchanged in behaviour. It is the default and the production store (ADR-0004).
- `DatabaseVersionRepository`: one row per Version in the `diagram_versions` table, holding the full content it recorded, with no subprocesses. It is written against the query builder and is verified on SQLite (the test database); PostgreSQL is not exercised by the suite.

The store is chosen by configuration: `VERSION_STORE=git|database` (`config/bpmn.php`, key `version_store`), defaulting to `git`. Behaviour shared by both adapters (Version state flags and names, history numbering, restore, post-save bookkeeping) lives in the `SharedVersionRepository` trait, so the adapters only differ in how they store and read Versions.

Both adapters must pass the same contract suite, `VersionRepositoryContract`, which exercises every interface operation: initialize, save, history order and numbering, get, exists, getMany, restore, rename, provisional and hidden Versions, and isolation between Diagrams. The database adapter also has a test asserting that it spawns no subprocess.

## Consequences

- Production behaviour is unchanged: the default store is git. The existing tests pass against it, with one call site updated (`VersionRepository::defaultName()` moved to `App\Support\VersionName::default()`).
- The database adapter stores full content per Version, the trade-off ADR-0004 rejected for storage efficiency. It exists for the browser demo; it is not a production store.
- Backups depend on the store. Git history lives in the diagrams directory, so it is in the archive. Database history lives in the database, so a restore depends on the database dump (`pg_dump`) being restored with it.
- The database adapter accepts only full 40-character Version ids, which is what `list()` returns. Git also resolves abbreviated commit ids.
- The `diagram_versions` table is created in every environment, even when the git store is in use, where it stays empty.
- The php-wasm spike stub (`spikes/php-wasm/overlay`) extends `VersionRepository`, which is now an interface, so it no longer loads. The spike should bind `VERSION_STORE=database` instead of the stub; it is left unchanged here.
