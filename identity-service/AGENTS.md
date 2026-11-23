# Identity Service Development Guide

This service uses Laravel 13 and PHP 8.3+.

## Architecture

Identity is a module-first Laravel service. Keep code for an Identity capability together under app/Modules/Identity:

- Presentation/Http: HTTP controllers, Form Requests, API Resources, middleware, and module routes.
- Application/Commands: immutable, typed input contracts for state-changing use cases.
- Application/Actions: execution/orchestration of Commands. This project uses Actions instead of adding a second CommandHandler class for each Command.
- Application/Queries: immutable, typed input contracts for independent read-only use cases.
- Application/QueryHandlers: execute Queries and return read-specific DTOs/projections; one Handler per real Query, without a generic bus or mediator.
- Application/ReadModels: narrow output models owned by the Application read use case, not Domain entities.
- Application/Contracts: ports required by Application from persistence, clocks, mail, hashing, ID/token generation, or transactions.
- Domain: entities/aggregate roots, value objects, policies, and business invariants.
- Infrastructure: Laravel/Eloquent adapters implementing Application contracts.

The direction of dependencies is inward:

Presentation -> Application -> Domain
Infrastructure -> Application contracts + Domain

Domain must not depend on Laravel, HTTP, Eloquent, SQL, queues, cache, or the service container. Application must not depend on HTTP Request/Response classes. Controllers remain thin adapters.

The conventional App\Models\User is the Laravel authentication/persistence model. Keep it separate from the Domain Identity\Domain\Entities\User; only Infrastructure persistence adapters may use the Eloquent model. The application service provider is the composition root that binds ports to adapters.

Do not create speculative modules such as Sessions, MFA, or Credentials until they have distinct use cases and business boundaries. Do not create empty Query, QueryHandler, ViewModel, or Result folders before a concrete read use case requires them.

## CQRS boundary

- Commands represent state-changing intent; Actions execute the use case and coordinate Domain behavior.
- UserRepository is the aggregate persistence boundary. A write use case may load an aggregate through findById/findByEmail, apply a Domain transition, and save it.
- Independent read-only use cases use a specific Query + QueryHandler + read port + narrow ReadModel; they must not be routed through UserRepository or hydrate Domain aggregates when a projection is sufficient.
- The user-list use case is implemented as ListUsersQuery -> ListUsersQueryHandler -> UserListReader -> UserListPage/UserListItem.
- Read and write currently use the same PostgreSQL database and schema. No replica, synchronization mechanism, mediator, command bus, or event sourcing is needed by default.
- Do not add a generic Query bus or a Query DTO + Handler + Service triplet. One specific Query and one specific Handler are enough.

## User-list API security

- GET /api/v1/identity/users is a server-to-server read endpoint protected by X-Identity-Admin-Token.
- Configure IDENTITY_USERS_READ_TOKEN with at least 64 random hexadecimal characters (32 random bytes).
- The secret belongs in a backend Secret Store. Never place it in browser code, a frontend environment variable exposed to the browser, a public Postman environment, or a committed .env file.
- The middleware fails closed when the secret is missing and compares the supplied value with hash_equals.
- This shared machine credential is not per-user RBAC and does not replace the Gateway's eventual authentication/authorization contract.

## Working conventions

- Follow the Exchange architecture contracts in ../platform/docs/architecture/development-contracts.fa.md and ../platform/docs/architecture/domain-driven-design.md.
- Keep changes scoped to Identity unless an explicit cross-service contract requires otherwise.
- Use immutable Commands as input DTOs; exclude transport-only fields such as password confirmation.
- Put orchestration in one Application Action per state-changing use case. Put invariants and state transitions in Domain.
- Use Repository ports for persisted aggregate roots, not for every table. Use focused read adapters for independent query paths when appropriate.
- Add or update automated tests for behavior changes.
- Do not add development tools or dependencies unless the task requires them and the change is agreed upon.
- Use the repository's existing Composer scripts for setup and testing.

## Local verification

Run these commands from identity-service/ when the local PHP and Composer environment is available:

    composer install
    php artisan test
