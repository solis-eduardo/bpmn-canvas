# 13: Transfer Diagram ownership

**What to build:** A Diagram Owner or an Administrator hands a Diagram to another User with no acceptance step. The previous Diagram Owner stays on as a Collaborator unless they tick "give up my access".

**Blocked by:** 12 (Diagram Owner takes over the Author's powers)

**Status:** ready-for-agent

- [ ] The current Diagram Owner or an Administrator can transfer to any User; nobody else can.
- [ ] The recipient becomes Diagram Owner immediately; the Author is unchanged.
- [ ] By default the previous Diagram Owner becomes a Collaborator in the same transaction; with the checkbox ticked, they get no access.
- [ ] An Administrator transferring someone else's Diagram follows the same rule for the previous Diagram Owner.
- [ ] Feature tests cover both checkbox outcomes, an Administrator transfer, and refusal for other Users.
