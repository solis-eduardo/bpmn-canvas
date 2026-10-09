# Diagram Manager role for Collaborators

Status: accepted

Only the Diagram Owner could invite or remove Collaborators (ADR-0006), so even a Diagram Owner who had delegated day-to-day work stayed the bottleneck for every access change. A Collaborator now carries a role, `collaborator` (default) or `manager` (**Diagram Manager**), giving the hierarchy Diagram Owner > Diagram Manager > Collaborator.

**Rules:**
- A Diagram Manager has all Collaborator editing powers, plus: invite Collaborators, approve or decline access requests, and remove plain Collaborators. Everything they grant is a plain Collaborator.
- Only the Diagram Owner (and, keeping its existing powers over Collaborators, an Administrator) can invite a Diagram Manager, promote a Collaborator, demote a Diagram Manager, or remove a Diagram Manager. A Diagram Manager cannot do any of this to anyone, themselves included, except that they can remove themselves (leave).
- Delete, ownership transfer, Share management and Workspace placement stay Diagram Owner-only (Administrator rules unchanged). Workspace Members get no new powers.
- On ownership transfer the previous Diagram Owner becomes a plain Collaborator, never a Diagram Manager automatically; the new Diagram Owner can promote them.
- Invitation as Diagram Manager, promotion, demotion and removal notify the affected User. Access request notifications still go to the Diagram Owner only.
- The role lives in a `role` column on `collaborators` (`collaborator` | `manager`); existing rows migrate to `collaborator`. Role changes are an optional `role` field on the existing Collaborator invite (`POST`) and update (`PUT`) endpoints, and the Collaborator list reports each entry's role.

**Why:** the rejected alternative, letting every Collaborator manage others, removes the Diagram Owner's control of who has access. A separate, opt-in tier keeps delegation explicit and revocable. Letting Diagram Managers appoint Diagram Managers was rejected so that the Diagram Owner alone decides who holds delegated power.

Extends ADR-0006 (who may manage Collaborators).
