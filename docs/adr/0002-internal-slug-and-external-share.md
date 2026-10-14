# Two separate access channels: internal Slug and external Share

The legacy stack used a single identifier — the file name — for both internal access and the viewer's public link (`?d=name.bpmn`), permanent and never expiring. The legacy README itself already noted this as a weakness: "obvious names can be guessed."

In the rebuild, we separated the two: the **Slug** is the internal, readable identifier, used in authenticated routes (Editor), mutable when renaming the Diagram, with the previous Slug permanently reserved and redirecting to the current Diagram (never reused). The **Share** is a separate mechanism for public access — a signed link, expiring in days (default 7, max 60), revocable at any time by the Author, with its own download permission (disabled by default).

This separation costs more complexity (two entities instead of one), but eliminates the legacy stack's structural weakness: the public link is never again "just the file name," and can be deactivated without affecting internal access to the Diagram.
