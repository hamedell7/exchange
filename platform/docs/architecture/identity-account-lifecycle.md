# Identity Account Lifecycle

## Account states

Identity owns the account lifecycle. The current state machine is intentionally small:

- **pending_verification** — created by registration; cannot be considered an active account until the email address is verified.
- **active** — email has been verified and the account can be used, subject to authentication policy.
- **suspended** — temporarily disabled by an authorized operational/security action.
- **closed** — terminal state.

Allowed transitions are:

    pending_verification -> active
    pending_verification -> closed
    active -> suspended
    active -> closed
    suspended -> active
    suspended -> closed

A failed-login lockout is deliberately not represented as a User state. It is a temporary authentication/rate-limiting concern and should not mutate the account aggregate for every failed attempt.

Existing rows are migrated according to their persisted verification timestamp: rows with `email_verified_at` remain active, while rows without it become pending_verification. New registrations are explicitly created as pending_verification.

## Account action tokens

Email verification and password reset use opaque 256-bit random tokens. Only SHA-256 hashes are persisted. Tokens are purpose-scoped, expiring and single-use. Issuing a new token replaces the previous outstanding token for the same user and purpose. Email links carry the token in the URL fragment; the frontend reads the fragment and submits the token in a POST body, so browsers do not send it in the HTTP request to the frontend server or in a Referer header.

The token is consumed in the same database transaction as the aggregate state change. This prevents a token from being consumed while its corresponding account update fails, and the row lock/conditional update prevents successful reuse under concurrent requests.

Email verification links have a 60-minute lifetime. Password reset links have a 30-minute lifetime. These durations are conservative application policy and can be changed deliberately.

## HTTP endpoints

| Method | Endpoint | Result |
|---|---|---|
| POST | /api/v1/identity/register | Creates a pending account and sends verification email |
| POST | /api/v1/identity/email/verification/verify | Consumes token and activates account |
| POST | /api/v1/identity/email/verification/resend | Sends a new verification email when applicable |
| POST | /api/v1/identity/password/forgot | Starts password reset without disclosing account existence |
| POST | /api/v1/identity/password/reset | Consumes token and replaces password hash |

Resend and forgot-password always return the same 202 response whether the email exists or not. This avoids account enumeration. They are rate-limited at the HTTP boundary by both caller IP and a hash of the normalized email identity.

## Request validation and API Resources

Presentation owns transport validation. Each JSON Form Request declares its allow-list of fields and rejects unknown properties, matching the OpenAPI additionalProperties: false contract. Password confirmation is transport-only and never enters an Application Command.

Application Commands remain immutable, typed PHP objects and carry no business-rule constants. The Domain User owns name validation, EmailAddress owns email syntax and identity normalization, and one Domain PasswordPolicy defines the password length rule shared by registration and password reset. Form Requests reuse Domain constants for early HTTP feedback; the Use Cases enforce the rules without depending on HTTP. No separate Password value object or custom policy exception is needed for this rule.

Responses use an explicit Laravel JsonResource that maps the Domain User to an allow-listed public representation. The Domain aggregate and Eloquent model are never serialized directly. Passwords, password hashes, remember tokens, normalized lookup columns and action-token hashes are not part of the public resource.

The book Domain-Driven Design with Laravel demonstrates consolidating Request, Resource and DTO roles into a Spatie Laravel Data class. That is a valid option, but this service keeps FormRequest, Application Command and API Resource separate because the input and output contracts differ and the Application boundary is already established. No new serialization package is needed for this vertical slice.

## Email delivery and failure handling

The account and action token are committed together. The email is sent after the transaction commits. If the mail transport fails, the account remains pending and the resend endpoint can issue a fresh link. A transactional outbox should replace this best-effort delivery step if guaranteed publication/retry becomes a product requirement.

## Deliberately deferred

Login, MFA, refresh tokens, device sessions, logout-all, and session revocation are not implemented in this slice. The Gateway-to-Identity authentication assertion/token contract has not yet been selected. Implementing a bespoke access-token format before that contract exists would couple every service to a premature decision. Laravel's database sessions table remains framework session storage, not a security-session registry.

Likewise, administrative suspend/reinstate/close endpoints must not be exposed until an authenticated administrative authorization policy is specified. The aggregate enforces the transitions now; the authorized application/API use cases are a subsequent slice.
