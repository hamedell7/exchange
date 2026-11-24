# Identity Service Architecture

**Status:** Implemented baseline  
**Scope:** identity-service only

Identity is one independently deployed Laravel microservice and the owner of customer accounts, credentials and account lifecycle. Its internal structure follows Laravel Service-Based conventions.

## Request flow

    Route -> Controller -> Form Request -> Service
                                      Service -> Eloquent Model / Query Builder / Laravel Facades
    Controller -> API Resource -> JSON

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

Services use Eloquent and Laravel facilities directly. There is no separate Domain/Application/Infrastructure stack, Repository port, Command DTO, Query Handler or persistence mapper for the current account flows. Shared helper services exist only for responsibilities used by multiple flows.

## Services

- `UserRegistrationService`: validates registration invariants and persists a new active account. Email verification is currently optional; no verification token or email is issued during registration.
- `LoginService`: checks the normalized email/password pair, rejects suspended and closed accounts, and issues a 30-day-by-default Sanctum personal access token.
- `EmailVerificationService`: retained for legacy pending accounts; consumes single-use verification tokens or resends a legacy verification email.
- `PasswordResetService`: issues opaque reset tokens and atomically consumes them with password replacement; existing access tokens are revoked after a successful reset.
- `UserQueryService`: performs a narrow Eloquent select and database pagination for the administrative user-list API.
- `AccountActionTokenService`: shared persistence operations for purpose-scoped, expiring, single-use password-reset and legacy verification tokens.
- `IdentityMailService`: sends password reset/legacy verification mail and safely logs delivery failures without logging addresses, URLs or tokens.

Controllers remain thin. Form Requests validate HTTP shape and reject unknown fields. API Resources explicitly whitelist public response fields.

## Authentication

Identity uses Laravel Sanctum personal access tokens for API authentication. Tokens are opaque, have a SHA-256 hash stored in `personal_access_tokens`, and expire after 30 days by default. The migration uses an ULID morph key to reference the Identity User model. Successful login returns a one-time display of the plain Bearer token and its expiry.

- `POST /api/v1/identity/login`: email/password login.
- `GET /api/v1/identity/me`: current token identity.
- `POST /api/v1/identity/logout`: revoke the current token.
- `Authorization: Bearer <token>`: authenticate protected Identity endpoints.

Email verification is not required for login. New registrations are active immediately, while historical `pending_verification` accounts can also sign in. Suspended and closed users cannot sign in. An active status does not imply email ownership has been verified.

Sanctum tokens are database-backed and currently validate only inside Identity. Gateway token validation and trusted identity propagation to Wallet/Trading are not implemented yet; no other service may inspect the Identity token table directly.

## Account and password rules

- Identity owns user identity, credentials and status.
- Email identity is case-insensitive. The original local-part casing is preserved; the domain is lowercased. The generated `email_normalized` column and named unique index are the final uniqueness authority.
- Passwords use Argon2id and the shared 15–128 Unicode code point rule.
- Login is rate-limited by IP and normalized email identity. Unknown email and incorrect password return the same generic 401 response.
- Reset and legacy verification tokens contain 256 bits of randomness. Only SHA-256 hashes are persisted. Tokens are purpose-scoped, expiring and single-use.
- Token consumption and the associated account update/password change use the same DB transaction. Row locking and conditional update prevent successful concurrent reuse.
- Password reset revokes all active Sanctum tokens for that account.
- `GET /api/v1/identity/users` remains a server-to-server endpoint protected by its separate `X-Identity-Admin-Token`; a normal user Bearer token is not an administrative credential.

## Cross-service boundary

Identity owns the User model, credentials and account status. Wallet and Trading may store only the opaque user ID as a foreign-service reference. They must not import Identity models or read its database. API Gateway token validation and trusted identity propagation remain a separate platform task.

## Deferred use cases

MFA, device-session management beyond token labels, logout-all, per-user administrative RBAC and token validation at Gateway/Wallet/Trading remain deferred until the cross-service authentication contract is defined.

See [Service-Based architecture](../../platform/docs/architecture/service-based-architecture.md) and [Identity Account Lifecycle](../../platform/docs/architecture/identity-account-lifecycle.md).
