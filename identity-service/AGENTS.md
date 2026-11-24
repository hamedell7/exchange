# Identity Service Development Guide

This service uses Laravel 13 and PHP 8.3+.

## Architecture

Identity follows a pragmatic Laravel Service-Based structure using the framework's normal conventions:

- `app/Http/Controllers`: thin HTTP controllers.
- `app/Http/Requests`: request validation and strict transport-field allow-lists.
- `app/Http/Resources`: explicit, allow-listed API responses.
- `app/Models`: Eloquent models and casts.
- `app/Services/Identity`: cohesive business operations and orchestration.
- `app/Enums`, `app/Support`, `app/Exceptions`: shared types and small helpers only when they remove real duplication.
- `app/Mail`: Laravel Mailable classes.

The normal flow is:

    Route -> Controller -> Form Request -> Service -> Eloquent Model / DB
                                   Controller -> API Resource -> JSON

Services may use Eloquent, DB transactions, Hash, Mail, Str and other Laravel facilities directly. This is intentional; the service layer is not required to hide Laravel behind a second interface.

Create one service per cohesive capability, not one class per method. Keep controllers thin. Keep API output explicit through Resources even though the services use Eloquent models.

## Avoid unnecessary architecture

Do not add these by default:

- Separate Domain, Application and Infrastructure layers.
- Command / Query objects and one Handler class per operation.
- Repository interfaces or implementations for routine Eloquent access.
- Interfaces for every service or Laravel facade.
- DTOs for simple controller-to-service calls.
- Value Objects for ordinary scalar fields.
- Generic CRUD services, base services, managers or mediators.
- Separate read/write models or databases without a measured need.

Use focused helper classes only where a rule or operation is genuinely shared. Use Eloquent scopes only for reusable query fragments, not as a replacement for understandable service logic.

## Identity security and data rules

- Identity owns its users, credentials, account lifecycle and database.
- Other services may keep only the opaque Identity user ID; they must not import this service's model or query its database.
- Email identity is case-insensitive. Preserve the local-part casing; lowercase the domain. The generated `email_normalized` column and named unique index are the final uniqueness authority.
- Passwords use Argon2id. Keep the 15–128 Unicode code-point rule consistent in registration and password reset. Do not add composition rules.
- Email verification and reset tokens contain 256 bits of randomness. Persist only SHA-256 hashes; enforce purpose, expiry and single use.
- Token consumption and account-state/password changes must share one DB transaction. Issuing a replacement token invalidates the previous outstanding token for the same user and purpose.
- Send mail only after the database transaction commits. Never log an email address, token or link on delivery failure.
- Forgot-password and resend-verification responses must not disclose whether an account exists.
- `GET /api/v1/identity/users` is server-to-server only. Require `X-Identity-Admin-Token`, fail closed when it is not configured, validate it as 64 lowercase hex characters and compare with `hash_equals`. Never put this credential in browser code or a public Postman environment. It is not per-user RBAC.

## Working conventions

- Follow [Exchange development contracts](../platform/docs/architecture/development-contracts.fa.md), [Service-Based architecture](../platform/docs/architecture/service-based-architecture.md), and [service boundaries](../platform/docs/architecture/service-boundaries.md).
- Keep changes scoped to Identity unless a public service contract requires otherwise.
- Add or update tests for every behavior change.
- Do not add packages or development tools unless they are required and agreed upon.
- Preserve the public API contract in `platform/contracts/openapi/identity/identity-v1.yaml`.

## Local verification

Run from `identity-service/`:

    composer install
    php artisan test
