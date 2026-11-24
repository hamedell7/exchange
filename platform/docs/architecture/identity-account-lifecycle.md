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

A failed-login lockout is deliberately not represented as a User state. It is a temporary authentication/rate-limiting concern and should not mutate the account state for every failed attempt.

Existing rows are migrated according to their persisted verification timestamp: rows with email_verified_at remain active, while rows without it become pending_verification. New registrations are explicitly created as pending_verification.

## Account action tokens

Email verification and password reset use opaque 256-bit random tokens. Only SHA-256 hashes are persisted. Tokens are purpose-scoped, expiring and single-use. Issuing a new token replaces the previous outstanding token for the same user and purpose. Email links carry the token in the URL fragment; the frontend reads the fragment and submits the token in a POST body, so browsers do not send it to the frontend server or in a Referer header.

The token is consumed in the same database transaction as the account update. This prevents a token from being consumed while its corresponding account update fails. A row lock and conditional update prevent successful reuse under concurrent requests.

Email verification links have a 60-minute lifetime. Password reset links have a 30-minute lifetime.

## HTTP endpoints

| Method | Endpoint | Result |
|---|---|---|
| POST | /api/v1/identity/register | Creates a pending account and sends verification email |
| POST | /api/v1/identity/email/verification/verify | Consumes token and activates account |
| POST | /api/v1/identity/email/verification/resend | Sends a new verification email when applicable |
| POST | /api/v1/identity/password/forgot | Starts password reset without disclosing account existence |
| POST | /api/v1/identity/password/reset | Consumes token and replaces password hash |
| GET | /api/v1/identity/users | Returns a filtered, paginated user list; server-to-server token required |

Resend and forgot-password always return the same 202 response whether the email exists or not. This avoids account enumeration. They are rate-limited at the HTTP boundary by both caller IP and a hash of the normalized email identity.

## User-list API

The user-list endpoint is implemented by UserQueryService using a narrow Eloquent select and database pagination. It supports optional search (name or email), status, page, and per_page (1–100; default 25). The response contains only id, name, email, status, email verification date, registration date, and pagination metadata. A separate Query object, Handler, Repository or Read Model is not required for this use case.

This is an administrative server-to-server endpoint protected by X-Identity-Admin-Token. Configure IDENTITY_USERS_READ_TOKEN with exactly 64 lowercase hexadecimal characters generated from 32 random bytes. Keep it only in a backend Secret Store. It must not be embedded in browser code or a public Postman environment. This shared credential gates machine access; it is not a replacement for the future end-user/admin-role authorization contract.

## Request validation and API Resources

Form Requests own transport validation and reject unknown JSON fields, matching the OpenAPI additionalProperties: false contract. Password confirmation is used only for request validation and is not passed to a Service.

Identity Services use Eloquent models and Laravel facilities directly. Password length is defined once in App\Support\Identity\PasswordPolicy and reused by registration, password reset and their Form Requests. Account states are represented by the small UserStatus enum. There is no separate Domain Aggregate, Command DTO, Repository adapter or persistence mapper for the current flows.

Responses use an explicit Laravel JsonResource allow-list. Passwords, password hashes, remember tokens, normalized lookup columns and action-token hashes are never part of the public resource.

## Email delivery and failure handling

The account and action token are committed together. The email is sent after the transaction commits. If the mail transport fails, the account remains pending and the resend endpoint can issue a fresh link. A transactional outbox should replace this best-effort delivery step if guaranteed publication/retry becomes a product requirement.

## Deliberately deferred

Login, MFA, refresh tokens, device sessions, logout-all, and session revocation are not implemented in this slice. The Gateway-to-Identity authentication assertion/token contract has not yet been selected. Implementing a bespoke access-token format before that contract exists would couple every service to a premature decision. Laravel's database sessions table remains framework session storage, not a security-session registry.

Likewise, administrative suspend/reinstate/close endpoints must not be exposed until an authenticated administrative authorization policy is specified. The status enum records the lifecycle states; transition endpoints will be implemented with their authorization rules when those use cases are defined.
