# Local↔Keycloak account linking by email, and an Administrator role that only turns on, never off automatically

Status: accepted

When enabling Keycloak login (ticket 08), two questions required a choice that isn't obvious and is expensive to reverse once real accounts already exist in production: (1) what to do when someone logs in via Keycloak and a local account with the same email already exists, and (2) how the Keycloak Administrator claim interacts with the existing manual flag.

**Linking by email:** Keycloak login resolves the account first by `keycloak_id`; if not found, it looks for a local account (`keycloak_id IS NULL`) with the same email and links that account to the Keycloak identity, instead of creating a second, duplicate account (which would also violate the `unique` constraint on `email`). The rejected alternative — always create a new account via Keycloak, leaving the local one orphaned — would break authorship of Diagrams already created by someone migrating from local login to SSO. This assumes the same email identifies the same person.

**Administrator only turns on, never off automatically:** the Keycloak claim/role can *grant* `is_admin`, but never revokes it — an `is_admin=true` set manually by another Administrator persists even if the claim disappears on a future login. The alternative (role always derived from the claim's current state) was rejected because it would make Administrator access dependent on configuration external to the system (Keycloak roles) even for accounts that never relied on that — a surprising side effect for whoever granted the role manually.
