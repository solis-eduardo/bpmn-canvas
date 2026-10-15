# Comment Threads live outside the Diagram content and bypass the Lock

Status: accepted

Collaboration was limited to editing under the Lock, so reviewing a process ("is this gateway right?") happened outside the tool (issue #251). We add **Comment Threads** of **Comments**, optionally anchored to a BPMN element id and shown as markers on the canvas.

**Decision:**
- Comments are database rows (`comment_threads`, `comments`), not BPMN content. They are not Versions, so writing one never creates a Version, never conflicts with a save (ADR-0004) and is not restored, compared or copied by a Fork. This is what lets them skip the Lock: nothing a Comment writes is shared with the Editor's unsaved canvas.
- Anyone who can open the Diagram (the `view` ability: Diagram Owner, Collaborator, Member, Administrator) can read, open, reply to, resolve and reopen Threads, in the Editor and in the read-only Viewer a locked Diagram shows. A Diagram hidden from a User answers 404 as everywhere else. Resolving and reopening are open to the same Users: a Thread belongs to the discussion, not to its first author. Public Shares (ADR-0002) show no Comments.
- An element anchor is the BPMN element id as text, with no foreign key to anything and not checked on write (the element may exist only in the writer's unsaved canvas). The server decides **orphaned** by checking that id against the Diagram's current saved content, on every listing, so a Thread follows an element through renames and moves, and becomes orphaned the moment a saved Version no longer contains it (restoring a Version brings it back). Orphaned Threads stay listed without a marker.
- A new Comment notifies the Diagram Owner and the Thread's other participants (Users with a Comment in it) with the existing notification system, which already applies each User's email preference (`PreferenceAwareNotification`). The author is never notified; a deactivated User (ADR-0020) and a User who can no longer open the Diagram are skipped. The notification carries no Comment text, so it can't leak content to a recipient who lost access.
- Comments can't be edited or deleted, and resolving is a state of the Thread (`resolved_at`, `resolved_by_id`), not a deletion; a reply reopens a resolved Thread. Comment bodies are plain text, rendered with `textContent`, limited to 2000 characters.
- Like the Lock and Collaborators, Comments are server-only: the standalone build (ADR-0019) has no other Users to talk to, so the shared core and `DiagramStore` are untouched. The Comments panel is a server-only script that works on the Modeler or the Viewer the page hands it.

**Why:** putting Comments in the BPMN XML (as `textAnnotation` or documentation) would force a Lock and a Version for every remark and mix review chatter into the model. Locking Comments behind the Lock would repeat the original problem: the reviewer is exactly who doesn't hold it. Tracking orphans with a stored flag was rejected because the saved content can change through any Version restore; deriving it on read cannot drift.

**Consequences:**
- Every listing parses the Diagram's saved BPMN once to collect its element ids.
- Markers are live: one appears as soon as its element is on the canvas, including unsaved edits. A Thread is shown as orphaned only when the saved content lacks its element and the canvas does too.
