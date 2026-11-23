# Identity Service Architecture

**Status:** Implemented baseline  
**Scope:** identity-service only

Identity is one bounded context and one independently deployed Laravel service. Its current module owns account registration, email verification, password-reset credentials, and the account lifecycle. Do not split these into speculative submodules until a real domain boundary and independent use cases justify them.

## Module structure

    app/
    ├── Http/                         # only app-wide HTTP concerns, when needed
    ├── Models/                       # Laravel authentication model
    ├── Providers/                    # service composition root
    └── Modules/
        └── Identity/
            ├── Presentation/Http/
            │   ├── Controllers/
            │   ├── Requests/
            │   ├── Resources/
            │   └── routes.php
            ├── Application/
            │   ├── Commands/
            │   ├── Actions/
            │   ├── Queries/          # add with the first independent read use case
            │   ├── QueryHandlers/    # add with the first independent read use case
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

The tree is a guide, not a requirement to create empty folders. In particular, Queries and QueryHandlers are created when a concrete read-only use case, its consumer, authorization rules, and output contract are known.

## Responsibilities and dependencies

### Presentation

HTTP Controllers are primary/driving adapters. They accept validated transport input, build the appropriate immutable Command, invoke an Application Action, and translate known outcomes into HTTP responses.

- Form Requests own HTTP input shape, allow-listed fields, confirmation fields, and transport validation.
- Commands exclude transport-only input such as password_confirmation.
- API Resources whitelist public output fields. Never return a Domain aggregate or Eloquent record directly.
- Resource Controllers are appropriate for conventional resource operations; Invokable Controllers are appropriate for standalone operations. Do not split a controller solely to make every method its own file.

The root routes/api.php loads the Identity module's route file; endpoint definitions remain beside the module's Presentation adapters.

### Application

- A Command is an immutable, typed input DTO expressing one state-changing intent.
- An Action executes one state-changing use case. In this project it is the command-side executor; a separate CommandHandler class would duplicate the Action responsibility.
- A Query is an immutable, typed request for data and must not change system state.
- A QueryHandler executes one specific Query and returns the consumer's read-specific DTO/projection. Do not return an Eloquent model or a write-side Domain Aggregate merely to reuse it.
- Application Contracts are ports for capabilities outside Application, such as persistence, hashing, mail, token/ID generation, clock, and transaction boundaries.
- UserRepository is the Domain Aggregate persistence boundary. It exposes add/save and aggregate reconstitution lookups (findById/findByEmail) needed by state-changing workflows.
- Independent read-only Queries use a focused read adapter and projection rather than going through UserRepository.
- Add a Result DTO only when another transport or consumer needs a stable output contract that is materially different from the Domain object.

### Domain

The User aggregate root protects account identity and lifecycle invariants. Value Objects express meaningful concepts such as EmailAddress, PasswordHash, and UserId. PasswordPolicy owns password-length policy shared by registration and password reset.

Domain has no dependency on Laravel, Eloquent, HTTP, SQL, Redis, queues, mail transports, or the service container.

### Infrastructure

Infrastructure implements Application ports. EloquentUserRepository persists and reconstitutes the User aggregate for command-side state transitions. A future independent Query will have a focused read adapter that can use Eloquent or Query Builder to return a narrow projection without hydrating the aggregate.

The Laravel App\Models\User remains the framework authentication/persistence model under the conventional app/Models path, separate from the Domain User. This follows the Exchange architecture contract: a conventional Eloquent model location is acceptable as long as Domain never imports it and only Infrastructure persistence code depends on it.

The Laravel service provider is the composition root for binding ports to concrete adapters. It is framework wiring, not a second business-service layer.

## Dependency direction

    Command path:
    Presentation Command -> Application Action -> Domain Aggregate
        -> UserRepository -> PostgreSQL

    Independent Query path (when introduced):
    Presentation -> Application Query -> QueryHandler
        -> read adapter -> DTO/projection <- PostgreSQL

Source dependencies point inward: Presentation depends on Application; Application depends on Domain and its own ports; Infrastructure depends inward to implement those ports.

## Read/write separation (CQRS)

CQRS is a logical separation of change-oriented and read-oriented use cases. It does not require separate databases.

- Command path: Commands + Actions coordinate Domain behavior; UserRepository loads and persists the Aggregate.
- Query path: a specific Query + QueryHandler reads a purpose-built projection through a focused read adapter. It does not call a write Action or hydrate an Aggregate when a projection is sufficient.
- Both paths currently use the same PostgreSQL database and schema. No replica, synchronization mechanism, or eventual consistency is introduced.

The existing verification-resend, password-reset-request, email-verification, and password-reset workflows are state-changing use cases. Their lookups load an Aggregate so the Action can evaluate its status or apply a Domain transition; these lookups correctly remain in UserRepository. They are not independent read-only Queries.

No independent read-only public use case is currently implemented. Login and user-profile reads remain out of scope until the Gateway authentication and authorization contract is defined. Do not expose users by ID or email through an unauthenticated endpoint.

When an independent read-only use case is defined, add one specific Query, one QueryHandler, and the smallest output DTO/projection needed by its consumer. A focused Eloquent/Query Builder adapter is sufficient initially. Introduce a separate Read Model storage or database replica only when requirements or measured performance justify them. No generic Query bus or mediator is required.

## Complexity guardrails

- No Value Object per database column.
- No Repository per table.
- No generic CRUD repository or generic service layer.
- No Command + CommandHandler + Action triplet for one use case; Action is the chosen command executor here.
- No Query DTO + QueryHandler + Query Service triplet for a simple read.
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
- Repository integration tests verify aggregate lookup by ID, normalized email, missing records, and Domain reconstitution.

CI is configured to run the Identity test suite against SQLite and PostgreSQL.
