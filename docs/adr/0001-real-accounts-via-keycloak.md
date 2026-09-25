# Real accounts via Keycloak, replacing shared Basic Auth

Status: accepted — Keycloak integration implemented (ticket 08); see ADR-0005 for the specific decisions on account linking and the Administrator role via claim

The legacy stack (nginx + WebDAV) used a single shared username/password via Basic Auth for everyone editing — no authorship, no trail of who did what. The Laravel rebuild introduces real User accounts, with Keycloak as the target identity source of truth (just-in-time provisioning on first login), and manual creation by an Administrator as an alternative path for cases that don't go through SSO.

We chose Keycloak (over Laravel's local authentication as the final target) because the organization already has that infrastructure piece, and because features that depend on authorship (Diagram Author, Collaborator, Administrator role) require stable individual identity — something the legacy stack's shared Basic Auth never offered.

**Note (historical):** this ADR was originally written while this project's client didn't yet exist in the organization's Keycloak, and SSO integration was deferred as a separate ticket. Ticket 08 implemented that integration; the two User origins (local and Keycloak) coexist as planned, with no change to the data model anticipated here.
