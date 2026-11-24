# Identity Service Architecture

**Status:** Implemented baseline  
**Scope:** identity-service only

Identity is one independently deployed Laravel microservice and the owner of customer accounts, credentials and account lifecycle. Its internal structure follows Laravel Service-Based conventions.

## Request flow

    Route
      -> Controller
      -> Form Request
      -> Service
      -> Eloquent Model / Query Builder / Laravel Facades
      -> API Resource
      -> JSON

## Application structure

    app/
    ├── Http/
    │   ├── Controllers/Api/V1/Identity/
    │   ├── Middleware/
    │   ├── Requests/Api/V1/Identity/
    │   └── Resources/Api/V1/Identity/
    ├── Mail/Identity/
    ├── Models/User.php
    ├── Services/Identity/
    ├── Enums/Identity/
    └── Support/Identity/

Services use Eloquent and Laravel facilities directly. There is no separate Domain/Application/Infrastructure stack, Repository port, Command DTO, Query Handler or persistence mapper for the current account flows. Shared helper services exist only for responsibilities used by multiple flows, such as account action token handling and mail delivery.

## Service responsibilities

- `UserRegistrationService`: validates registration invariants, persists the pending account and verification token atomically, then sends mail after commit.
- `EmailVerificationService`: consumes single-use verification tokens and transitions pending users to active; resend keeps account existence private.
- `PasswordResetService`: issues opaque reset tokens and atomically consumes them with password replacement.
- `UserQueryService`: performs a narrow Eloquent select and database pagination for the administrative user-list API.
- `AccountActionTokenService`: shared persistence operations for purpose-scoped, expiring, single-use tokens.
- `IdentityMailService`: sends mail and safely logs delivery failures without logging addresses, URLs or tokens.

Controllers remain thin. Form Requests validate HTTP shape and reject unknown fields. API Resources explicitly whitelist public response fields.

## Account lifecycle and security rules

- States: `pending_verification`, `active`, `suspended`, `closed`.
- New accounts begin as `pending_verification`.
- Email identity is case-insensitive. The original address is preserved for display/delivery; the generated `email_normalized` column and named unique index are the database uniqueness authority.
- Password hashing uses Argon2id and the shared 15–128 Unicode code point rule.
- Verification and reset tokens contain 256 bits of randomness. Only SHA-256 hashes are persisted. Tokens are purpose-scoped, expiring and single-use.
- Token consumption and the associated account update/password change use the same DB transaction. Row locking and conditional update prevent successful concurrent reuse.
- Sending mail happens only after transaction commit. A mail failure does not expose the address/token in logs; verification can be recovered through resend.
- Forgot-password and resend-verification always return the same 202 response for existing and unknown addresses. Both are rate-limited by IP and normalized email hash.

## User-list API

`GET /api/v1/identity/users` supports optional search (name or email), status, page and per_page (1–100; default 25). The query selects only id, name, email, status, email verification date and registration date; pagination is performed by the database.

The endpoint is server-to-server only and requires `X-Identity-Admin-Token`. Configure `IDENTITY_USERS_READ_TOKEN` as exactly 64 lowercase hexadecimal characters from 32 random bytes. Missing/malformed configuration fails closed with 503; missing or incorrect credentials return 401. Keep the token in backend secret storage only. This shared credential is not per-user authentication or administrative RBAC.

## Cross-service boundary

Identity owns the User model, credentials and account status. Wallet and Trading may store only the opaque user ID as a foreign-service reference. They must not import Identity models or read its database. API Gateway authentication and authorization contracts remain a separate platform concern.

## Deferred use cases

Login, MFA, refresh tokens, device sessions, logout-all, session revocation and administrative lifecycle endpoints remain deferred until the Gateway authentication and admin authorization contracts are defined. No new token format, session model or event contract should be designed speculatively.

See [Service-Based architecture](../../platform/docs/architecture/service-based-architecture.md) and [Identity Account Lifecycle](../../platform/docs/architecture/identity-account-lifecycle.md).
