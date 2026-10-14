# One recorded login method per User

Status: accepted

Each User has exactly one login method, `local` or `keycloak`, stored explicitly on the account. Before, the method was implied by `keycloak_id`, and a linked account could use both local and Keycloak login. That made it impossible to say which method an account uses, to filter Users by it, or to let an Administrator switch it.

**Rules:** a manually created User starts as `local` (the create form has no method field); the Administrator can change the method later, on the edit form (`keycloak` needs no password and links at the first verified Keycloak login); a Keycloak login with a verified email that matches a `local` account overwrites the method with `keycloak`. A `keycloak` User's local password stops being valid but is kept, so switching back to `local` restores it. A `keycloak` User attempting local login gets the generic invalid-credentials error, never a hint about the method. Without Keycloak configured, `keycloak` Users cannot sign in, the Administrator can still switch them to `local`, and the `keycloak` option is hidden from the edit form and the filter. On every Keycloak login of a linked account, the name is refreshed from the token, and the email too when `email_verified` is true and no other account holds it (changes are logged). Existing accounts are migrated by `keycloak_id`: not null → `keycloak`, otherwise `local`.

**Why:** the rejected alternative, keeping both methods usable on one account, left the "login method" ambiguous and kept a local password as a second way in after an account moved to SSO. Keeping the dormant password (instead of deleting it) makes the Administrator's switch back cheap and reversible.

Supersedes the linking decision in ADR-0005; the email-verification requirement and the Administrator-claim rule there still hold.
