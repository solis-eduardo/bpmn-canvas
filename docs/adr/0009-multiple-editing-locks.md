# A User may hold multiple Editing Locks

An Editing Lock belongs to one Diagram, so a User may hold Locks on multiple Diagrams at once. Navigation, closing the Editor, and inactivity never release a Lock: only an explicit release by its holder or an Administrator does. A different editor may request a held Lock and notify its holder, but the request never triggers an automatic transfer, regardless of elapsed time. This avoids silently releasing a Diagram when its editor changes screens and keeps the release decision with an authorized actor.
