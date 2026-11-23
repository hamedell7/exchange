# Identity Service Architecture

**Status:** Implemented baseline  
**Scope:** identity-service only

Identity is one bounded context and one independently deployed Laravel service. Its current module owns account registration, email verification, password-reset credentials, account lifecycle, and the administrative user-list read capability. Do not split these into speculative submodules until a real domain boundary and independent use cases justify them.

## Module structure

    app/
    ├── Http/                         # only app-wide HTTP concerns, when needed
    ├── Models/                       # Laravel authentication model
    ├── Providers/                    # service composition root
    └── Modules/
        └── Identity/
            ├── Presentation/Http/
            │   ├── Controllers/
            │   ├── Middleware/
            │   ├── Requests/
            │   ├── Resources/
            │   └── routes.php
            ├── Application/
            │   ├── Commands/
            │   ├── Actions/
            │   ├── Queries/
            │   ├── QueryHandlers/
            │   ├── ReadModels/
            │   ├── Contracts/
            │   ├── Enums/
            │   └── Exceptions/
            ├── Domain/
            │   ├── Entities/
            │   ├── Enums/
            │   ├── Policies/
            │   └── ValueObjects/
            └── Infrastructure/
                ├── Clock/
                ├── Database/
                ├── Hashing/
                ├── Identifiers/
                ├── Mail/
                ├── Persistence/Eloquent/
                └── Tokens/

The tree is a guide, not a requirement to create empty folders. Queries, QueryHandlers, and ReadModels exist because the user-list read use case now needs them.

## Responsibilities and dependencies

### Presentation

HTTP Controllers are primary/driving adapters. They validate transport input, construct an Application Command or Query, invoke the corresponding executor, and translate the result into an HTTP response.

- Form Requests own HTTP input shape, allow-listed fields, and transport validation.
- Commands exclude transport-only input such as password_confirmation.
- Queries accept validated read filters such as page, per_page, search, and status.
- API Resources whitelist public output fields. Never return a Domain aggregate or Eloquent record directly.
- The user-list endpoint is protected by server-to-server credential middleware. Its secret must never be shipped to browser code.

The root routes/api.php loads the Identity module's route file; endpoint definitions remain beside the module's Presentation adapters.

### Application

- A Command is an immutable, typed input DTO expressing one state-changing intent.
- An Action executes one state-changing use case. In this project it is the command-side executor; a separate CommandHandler class would duplicate the Action responsibility.
- A Query is an immutable, typed request for data and must not change system state.
- A QueryHandler executes one specific Query and returns a read-specific DTO/projection. Do not return an Eloquent model or a write-side Domain Aggregate merely to reuse it.
- Application Contracts are ports for capabilities outside Application, such as persistence, hashing, mail, token/ID generation, clock, and transaction boundaries.
- UserRepository is the Domain Aggregate persistence boundary. It exposes add/save and aggregate reconstitution lookups (findById/findByEmail) needed by state-changing workflows.
- UserListReader is a read port for the user-list projection. Independent read-only Queries use it rather than UserRepository.
- UserListItem and UserListPage are read models for the list response; they are not Domain entities.

### Domain

The User aggregate root protects account identity and lifecycle invariants. Value Objects express meaningful concepts such as EmailAddress, PasswordHash, and UserId. PasswordPolicy owns password-length policy shared by registration and password reset.

Domain has no dependency on Laravel, Eloquent, HTTP, SQL, Redis, queues, mail transports, or the service container.

### Infrastructure

Infrastructure implements Application ports. EloquentUserRepository persists and reconstitutes the User aggregate for command-side state transitions. EloquentUserListReader performs a narrow SQL projection for list queries, selects only public fields, applies filtering and stable ordering, and paginates in the database without hydrating User aggregates.

The Laravel App\Models\User remains the framework authentication/persistence model under the conventional app/Models path, separate from the Domain User. This follows the Exchange architecture contract: a conventional Eloquent model location is acceptable as long as Domain never imports it and only Infrastructure persistence code depends on it.

The Laravel service provider is the composition root for binding ports to concrete adapters. It is framework wiring, not a second business-service layer.

## Dependency direction

    Command path:
    Presentation Command -> Application Action -> Domain Aggregate
        -> UserRepository -> PostgreSQL

    Independent Query path:
    Presentation -> Application Query -> QueryHandler
        -> UserListReader -> SQL projection -> ReadModel -> API Resource

Source dependencies point inward: Presentation depends on Application; Application depends on Domain and its own ports; Infrastructure depends inward to implement those ports.

## Read/write separation (CQRS)

CQRS is a logical separation of change-oriented and read-oriented use cases. It does not require separate databases.

- Command path: Commands + Actions coordinate Domain behavior; UserRepository loads and persists the Aggregate.
- Query path: a specific Query + QueryHandler reads a purpose-built projection through a focused read adapter. It does not call a write Action or hydrate an Aggregate when a projection is sufficient.
- Both paths currently use the same PostgreSQL database and schema. No replica, synchronization mechanism, or eventual consistency is introduced.

The user-list use case is the first independent Query in Identity. ListUsersQuery accepts optional search and status filters plus bounded pagination. ListUsersQueryHandler delegates to UserListReader; EloquentUserListReader selects only id, name, email, status, email verification date, and registration date. Results are returned as UserListItem and UserListPage. Search matches name or normalized email case-insensitively. Results are ordered newest-first with an id tie-breaker.

The existing verification-resend, password-reset-request, email-verification, and password-reset workflows are state-changing use cases. Their lookups load an Aggregate so the Action can evaluate its status or apply a Domain transition; these lookups correctly remain in UserRepository. They are not independent read-only Queries.

GET /api/v1/identity/users is a server-to-server administrative read endpoint. It uses X-Identity-Admin-Token, compared in constant time. The service fails closed with 503 when IDENTITY_USERS_READ_TOKEN is missing or not exactly 64 lowercase hexadecimal characters, and returns 401 for an absent or incorrect token. Generate a random 32-byte hex secret and keep it in a server-side Secret Store; never expose it to a browser, frontend bundle, or public Postman environment. This credential is a machine-level access gate, not a substitute for future per-user administrative roles or the Gateway's final authentication/authorization contract.

No user-list API is exposed without that secret. Login, user profile reads for an end user, and user-specific administrative RBAC remain out of scope until the Gateway authentication and authorization contract is defined. Do not expose users by ID or email through an unauthenticated endpoint.

A focused Eloquent/Query Builder adapter is sufficient initially. Introduce separate Read Model storage or a database replica only when requirements or measured performance justify them. No generic Query bus or mediator is required.

## Complexity guardrails

- No Value Object per database column.
- No Repository per table.
- No generic CRUD repository or generic service layer.
- No Command + CommandHandler + Action triplet for one use case; Action is the chosen command executor here.
- No generic Query bus or Query DTO + QueryHandler + Query Service triplet for a simple read.
- No Job or queue for every Action.
- No Event Sourcing or second database by default.
- No empty architecture folders or speculative submodules.
- Use a transaction only when multiple writes must commit or roll back together.
- Database unique constraints remain the final protection against concurrent duplicate identities.

## Tests

- Domain: pure unit tests for Value Objects, invariants, and state transitions.
- Application: unit tests with mocked/fake ports.
- Infrastructure: integration tests for Eloquent mapping, transactions, and database-specific constraints.
- Presentation: Laravel feature tests for routes, validation, status codes, response whitelists, and rate limits.
- User-list API tests verify token enforcement, fail-closed configuration, filtering, pagination, strict query parameters, and sensitive-field exclusion.

CI is configured to run the Identity test suite against SQLite and PostgreSQL.
