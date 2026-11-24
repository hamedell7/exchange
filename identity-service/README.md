# Identity Service

Laravel 13 service responsible for user accounts, credentials and account lifecycle.

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

Service boundaries, security rules and the cross-service ownership model are documented in:

- [Service-Based architecture](../platform/docs/architecture/service-based-architecture.md)
- [Exchange development contracts](../platform/docs/architecture/development-contracts.fa.md)
- [Service boundaries](../platform/docs/architecture/service-boundaries.md)
- [Identity account lifecycle](../platform/docs/architecture/identity-account-lifecycle.md)

## Local development

Run the service using its Compose file from the repository root:

    docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
    docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan migrate
    docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan test

Set IDENTITY_USERS_READ_TOKEN to a 64-character lowercase hex string generated from 32 random bytes. Keep it in a backend secret store or the local untracked .env file only.

The user-list endpoint is server-to-server only. Its shared token is not per-user authentication or administrative RBAC.
