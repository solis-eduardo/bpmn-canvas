# Optimistic Editing Lock with automatic timeout-based transfer

The legacy stack was explicit about having no concurrent editing lock at all: "if two people save the same file, the last one wins." That was acceptable with a single shared username/password, but with individual accounts (ADR-0001) and per-Diagram Collaborators, silent collisions become a real risk of lost work.

We introduced an Editing Lock: whoever opens a Diagram's Editor holds it, blocking others from opening the same Editor at the same time. Instead of a rigid lock (which would lock the Diagram indefinitely if someone forgot it open), anyone who runs into it can request the lock via polling — if the holder doesn't respond within 30 seconds, the lock is transferred to the requester automatically. A Fork (independent copy of the Diagram) is available as an immediate alternative, and an Administrator can force a release at any time as a last resort.

We chose this design — optimistic lock with a short timeout and automatic fallback — over (a) keeping "no lock" as in the legacy stack, which risks lost work, or (b) a rigid lock with no rescue mechanism, which would stall the workflow whenever someone forgot the Editor open.
