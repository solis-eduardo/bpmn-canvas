# Explicitly released Lock

The legacy stack was explicit about having no concurrent editing lock at all: "if two people save the same file, the last one wins." That was acceptable with a single shared username/password, but with individual accounts (ADR-0001) and per-Diagram Collaborators, silent collisions become a real risk of lost work.

A Lock is held per Diagram: opening another Editor does not replace or release a User's existing Locks. The holder must explicitly release each Lock; leaving or closing the Editor and inactivity do not release it. Other eligible Users can request a held Lock, which notifies the holder but never transfers the Lock automatically, including after 30 seconds. An Administrator can explicitly force-release a Lock. A Fork (independent copy of the Diagram) remains an immediate alternative for someone who cannot wait for release.

We chose explicit release over automatic release on navigation or inactivity because browser/tab lifecycle and user activity are not reliable indicators that editing has ended; automatic transfer after a request deadline could therefore take the Diagram away from an active holder. The trade-off is that an abandoned Lock can block editing indefinitely. The visible holder, request notification, Fork option, and Administrator force-release provide ways to resolve that case without silently transferring ownership of the Lock.
