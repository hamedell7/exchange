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

Create a folder only when a concrete capability needs it. An Application/Queries folder is introduced when an independent read-only use case exists; it is not a mandatory empty layer.

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
- An Action is the implementation of one state-changing use case. It coordinates Domain behavior and required output ports; it does not own business invariants.
- Application Contracts are ports for capabilities outside Application, such as persistence, hashing, mail, token/ID generation, clock, and transaction boundaries.
- UserRepository is the write port and exposes only aggregate persistence operations (add/save).
- UserReader is the read port and exposes aggregate lookup operations. The current write workflows use it only to load an aggregate before applying a Domain transition.
- Do not add a second Service or Handler that repeats an Action's responsibility.
- Add a Result DTO only when another transport or consumer needs a stable output contract that is materially different from the Domain object.

### Domain

The User aggregate root protects account identity and lifecycle invariants. Value Objects express meaningful concepts such as EmailAddress, PasswordHash, and UserId. PasswordPolicy owns password-length policy shared by registration and password reset.

Domain has no dependency on Laravel, Eloquent, HTTP, SQL, Redis, queues, mail transports, or the service container.

### Infrastructure

Infrastructure implements Application ports. EloquentUserRepository handles writes and maps Domain aggregates to persisted records. EloquentUserReader owns read queries and reconstitutes Domain aggregates for workflows that must perform a subsequent state transition. Hashing, mail, clock, token generation, and transaction implementations are also infrastructure adapters.

The Laravel App\Models\User remains the framework authentication/persistence model under the conventional app/Models path, separate from the Domain User. This follows the Exchange architecture contract: a conventional Eloquent model location is acceptable as long as Domain never imports it and only Infrastructure persistence code depends on it.

The Laravel service provider is the composition root for binding ports to concrete adapters. It is framework wiring, not a second business-service layer.

## Dependency direction

    HTTP Request
        |
        v
    Presentation Controller + Form Request
        |
        v
    Application Command -> Application Action
        |
        v
    Domain Aggregate / Value Objects / Policies
        ^
        |
    Infrastructure adapters implement Application Contracts
        |
        v
    Eloquent / PostgreSQL / Laravel Hash / Mail / Clock

Source dependencies point inward: Presentation depends on Application; Application depends on Domain and its own ports; Infrastructure depends inward to implement those ports.

## Read/write separation (CQRS)

CQRS is introduced logically, not by splitting the database:

- Write path: Presentation Command -> Application Action -> Domain transition -> UserRepository -> PostgreSQL.
- Read access: Application uses UserReader -> EloquentUserReader -> PostgreSQL. Read responsibilities no longer live on the write repository.
- The same PostgreSQL database and schema serve both paths. No replica, synchronization mechanism, or eventual consistency is introduced.

The existing verification-resend, password-reset-request, email-verification, and password-reset workflows need to locate an existing aggregate before they can decide whether or how to change it. Those lookups now pass through UserReader, while persistence of state changes passes through UserRepository.

This is the first logical CQRS boundary, not a claim that the service already has a separate public Query API or optimized read model. Login and user-profile reads remain out of scope until the Gateway authentication and authorization contract is defined. Do not expose users by ID or email through an unauthenticated endpoint.

When an independent read-only use case is defined, add one focused Application Query and a read-specific DTO/projection for its consumer. Prefer a focused Eloquent query for joins and projections; introduce a dedicated Read Model or database replica only when requirements or measured performance justify them. Do not add a generic query bus or a Query DTO + Query Handler + Query Service triplet for a simple read.

Do not create generic repositories for every table. Repository ports are for persisted aggregate roots; read queries should not be forced through the write repository.

## Complexity guardrails

- No Value Object per database column.
- No Repository per table.
- No generic CRUD repository or generic service layer.
- No Command + Handler + Action triplet for one use case.
- No Query DTO + Query Handler + Query Service triplet for a simple read.
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
- Read adapter: integration tests verify ID lookup, normalized-email lookup, missing records, and Domain reconstitution.

CI is configured to run the Identity test suite against SQLite and PostgreSQL.
