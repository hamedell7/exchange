# Identity Service

Laravel 13 service responsible for user accounts, credentials, password authentication and account lifecycle.

## Internal architecture

Identity uses a pragmatic Laravel Service-Based structure:

    app/
    ├── Http/
    │   ├── Controllers/Api/V1/Identity/
    │   ├── Requests/Api/V1/Identity/
    │   ├── Resources/Api/V1/Identity/
    │   └── Middleware/
    ├── Models/User.php
    ├── Services/Identity/
    ├── Enums/Identity/
    ├── Support/Identity/
    └── Mail/Identity/

The normal request flow is Controller -> Form Request -> Service -> Eloquent/DB. Services use Laravel facilities directly; routine Eloquent access does not require Repository interfaces, Command DTOs, Query Handlers or a separate Domain/Infrastructure layer.

## Authentication

- Register: `POST /api/v1/identity/register` — creates an active user; email verification is currently not required.
- Login: `POST /api/v1/identity/login` — accepts email, password and optional device_name.
- Current user: `GET /api/v1/identity/me` — requires `Authorization: Bearer <token>`.
- Logout: `POST /api/v1/identity/logout` — revokes the current Sanctum token.

Sanctum personal access tokens are opaque, stored hashed, and expire after 30 days by default. Configure the lifetime in minutes through `IDENTITY_ACCESS_TOKEN_LIFETIME`. Email verification is not required for login; suspended and closed accounts are rejected. Password reset revokes all previously issued personal access tokens.

The Sanctum token authenticates Identity routes only. Gateway/Wallet/Trading token validation and trusted identity propagation are not implemented yet; do not send this token to those services expecting them to recognize it.

## Architecture and contracts

- [Service-Based architecture](../platform/docs/architecture/service-based-architecture.md)
- [Exchange development contracts](../platform/docs/architecture/development-contracts.fa.md)
- [Service boundaries](../platform/docs/architecture/service-boundaries.md)
- [Identity account lifecycle](../platform/docs/architecture/identity-account-lifecycle.md)
- [Identity Service Architecture](docs/architecture.md)

## Local development

Run the service using its Compose file from the repository root:

    docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
    docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan migrate
    docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan test

Set `IDENTITY_USERS_READ_TOKEN` to a 64-character lowercase hex string generated from 32 random bytes. Keep it in a backend secret store or the local untracked `.env` file only.

The user-list endpoint is server-to-server only. Its shared token is not per-user authentication or administrative RBAC.
