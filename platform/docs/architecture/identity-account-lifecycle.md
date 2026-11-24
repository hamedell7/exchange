# Identity Account Lifecycle

## Account states

Identity owns the account lifecycle. The current states are:

- **pending_verification** — legacy state for accounts created before email verification became optional. These accounts are allowed to sign in while they are not suspended or closed.
- **active** — enabled account. In the current policy this does not imply that the email address has been verified.
- **suspended** — temporarily disabled by an authorized operational/security action; sign-in is rejected.
- **closed** — terminal state; sign-in is rejected.

New registrations are created as `active` immediately. `email_verified_at` remains null unless the legacy verification flow is explicitly completed. Do not infer ownership of the email address from `status=active`.

A failed-login lockout is deliberately not represented as a User state. It is a temporary authentication/rate-limiting concern and should not mutate the account state for every failed attempt.

## Password authentication

`POST /api/v1/identity/login` authenticates by the case-insensitive normalized email identity and password. Email verification is not required. Suspended and closed accounts are rejected with the same generic credential error used for unknown email addresses and incorrect passwords.

Successful login issues an opaque Laravel Sanctum personal access token:

- The raw token is returned only in the login response.
- The database stores only its SHA-256 hash.
- Tokens expire after 30 days by default; configure `IDENTITY_ACCESS_TOKEN_LIFETIME` in minutes to change the lifetime.
- Tokens are associated with a device name and have `identity:read` and `identity:write` abilities.
- Send it on authenticated Identity API requests as `Authorization: Bearer <token>`.
- `GET /api/v1/identity/me` returns the authenticated account.
- `POST /api/v1/identity/logout` revokes only the current token.
- Resetting a password revokes all existing personal access tokens for that account.

Login is rate-limited by caller IP and normalized email identity. Failed login responses do not disclose whether an account exists.

Sanctum personal access tokens are database-backed and currently authenticate requests to Identity only. The API Gateway does not yet validate these tokens or establish a trusted identity assertion for Wallet and Trading. Do not assume that receiving a token grants access to other services; the cross-service issuer/audience/validation contract remains a separate implementation step.

## Legacy email-verification tokens

Email verification is currently optional. The endpoints `POST /api/v1/identity/email/verification/resend` and `POST /api/v1/identity/email/verification/verify` are retained for accounts that were created in the old pending-verification state. New registration does not issue a verification token or send a verification email. These endpoints are deprecated for new account flows.

Legacy verification and password reset use opaque 256-bit random tokens. Only SHA-256 hashes are persisted. Tokens are purpose-scoped, expiring and single-use. Issuing a new token replaces the previous outstanding token for the same user and purpose. Email links carry the token in the URL fragment; the frontend reads the fragment and submits the token in a POST body, so browsers do not send it to the frontend server or in a Referer header.

The token is consumed in the same database transaction as the account update. This prevents a token from being consumed while its corresponding account update fails. A row lock and conditional update prevent successful reuse under concurrent requests.

Legacy email verification links have a 60-minute lifetime. Password reset links have a 30-minute lifetime.

## HTTP endpoints

| Method | Endpoint | Result |
|---|---|---|
| POST | /api/v1/identity/register | Creates an active account; no email verification required |
| POST | /api/v1/identity/login | Validates email/password and issues a Sanctum Bearer token |
| GET | /api/v1/identity/me | Returns the authenticated user |
| POST | /api/v1/identity/logout | Revokes the current Bearer token |
| POST | /api/v1/identity/email/verification/verify | Legacy: consumes token and verifies an old pending account |
| POST | /api/v1/identity/email/verification/resend | Legacy: sends a new verification email for an old pending account |
| POST | /api/v1/identity/password/forgot | Starts password reset without disclosing account existence |
| POST | /api/v1/identity/password/reset | Consumes token, replaces password hash and revokes access tokens |
| GET | /api/v1/identity/users | Returns a filtered, paginated user list; server-to-server token required |

Forgot-password and legacy resend-verification always return the same 202 response whether the email exists or not. This avoids account enumeration. They are rate-limited at the HTTP boundary by both caller IP and a hash of the normalized email identity.

## User-list API

The user-list endpoint is implemented by `UserQueryService` using a narrow Eloquent select and database pagination. It supports optional search (name or email), status, page, and per_page (1–100; default 25). The response contains only id, name, email, status, email verification date, registration date, and pagination metadata.

This is an administrative server-to-server endpoint protected by `X-Identity-Admin-Token`. Configure `IDENTITY_USERS_READ_TOKEN` with exactly 64 lowercase hexadecimal characters generated from 32 random bytes. Keep it only in a backend Secret Store. It must not be embedded in browser code or a public Postman environment. This shared credential gates machine access; it is not a replacement for the future end-user/admin-role authorization contract.

## Request validation and API Resources

Form Requests own transport validation and reject unknown JSON fields, matching the OpenAPI `additionalProperties: false` contract. Password confirmation is used only for registration/reset validation and is not passed to a Service.

Identity Services use Eloquent models and Laravel facilities directly. Password length is defined once in `App\Support\Identity\PasswordPolicy` and reused by registration, password reset and their Form Requests. Account states are represented by the small `UserStatus` enum. There is no separate Domain Aggregate, Command DTO, Repository adapter or persistence mapper for the current flows.

Responses use explicit Laravel JsonResource allow-lists. Passwords, password hashes, remember tokens, normalized lookup columns, personal access token hashes and action-token hashes are never part of public resources.

## Email delivery and failure handling

New registration does not send email. Password-reset email delivery still happens after the reset token transaction commits. If mail delivery fails, sensitive email/token/link data are not written to application logs.

## Deliberately deferred

MFA, device-session management beyond Sanctum token names, logout-all, granular per-user administrative roles and service-to-service token validation remain deferred. The Gateway-to-Identity authentication assertion/validation contract has not yet been selected. Sanctum tokens must not be treated as JWTs or as self-verifying tokens by other services.

Administrative suspend/reinstate/close endpoints must not be exposed until an authenticated administrative authorization policy is specified.
