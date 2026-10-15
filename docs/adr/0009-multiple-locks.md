# A User may hold multiple Locks

A Lock belongs to one Diagram, so a User may hold Locks on multiple Diagrams at once. Navigation and closing the Editor never release a Lock by themselves: a Lock is released explicitly by its holder or an Administrator, or by expiry after 5 minutes without a Heartbeat (ADR-0003), which saves any unsaved content as a provisional Version (but see the amendment below for a holder who loses the right to edit). Each Lock expires independently, so a User keeping one Editor active does not keep their other Locks alive. A different editor may request a held Lock and notify its holder, but the request never triggers an automatic transfer, regardless of elapsed time. This avoids silently releasing a Diagram when its editor changes screens and keeps the transfer decision with an authorized actor.

## Amendment: a Lock is also released when its holder loses edit rights

A Lock no longer ends only by explicit release or expiry: it is also released as soon as its holder can no longer edit the Diagram, with the holder's draft discarded rather than kept as a provisional Version (see the amendment to ADR-0003). Because a User may hold Locks on several Diagrams, one access change can affect several Locks, such as a Workspace membership change; each Lock is still judged independently by whether its holder can edit that Diagram.
