# Identity Service Architecture

**Status:** Implemented baseline  
**Scope:** `identity-service` only

Identity is one bounded context and one independently deployed Laravel service. Its current module owns account registration, email verification, password-reset credentials, and the account lifecycle. Do not split these into speculative submodules until a real domain boundary and independent use cases justify it.

## Module structure

```text
app/
├── Http/                         # only app-wide HTTP concerns, when needed
├── Models/                       # Laravel authentication model
├── Providers/                    # service composition root
└── Modules/
    └── Identity/
        ├── Presentation/
        │   └── Http/
        │       ├── Controllers/
        │       ├── Requests/
        │       ├── Resources/
        │       └── routes.php
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
            ├── Persistence/
            └── Tokens/
```

Create a folder only when a concrete capability needs it. In particular, no empty Query/ViewModel, Result, Domain Service, Factory, or extra module is required yet.

## Responsibilities and dependencies

### Presentation

HTTP Controllers are primary/driving adapters. They accept validated transport input, build the appropriate immutable Command, invoke an Application Action, and translate known outcomes into HTTP responses.

- Form Requests own HTTP input shape, allow-listed fields, confirmation fields, and transport validation.
- Commands exclude transport-only input such as `password_confirmation`.
- API Resources whitelist public output fields. Never return a Domain aggregate or Eloquent record directly.
- Resource Controllers are appropriate for conventional resource operations; Invokable Controllers are appropriate for standalone operations. Do not split a controller solely to make every method its own file.

The root `routes/api.php` loads the Identity module's route file; endpoint definitions remain beside the module's Presentation adapters.

### Application

- A Command is an immutable, typed input DTO expressing one state-changing intent.
- An Action is the implementation of one use case. It coordinates Domain behavior and required output ports; it does not own business invariants.
- Application Contracts are ports for capabilities outside Application, such as persistence, hashing, mail, token/ID generation, clock, and transaction boundaries.
- Do not add a second Service or Handler that repeats an Action's responsibility.
- Add a Result DTO only when another transport or consumer needs a stable output contract that is materially different from the Domain object.

### Domain

The `User` aggregate root protects account identity and lifecycle invariants. Value Objects express meaningful concepts such as `EmailAddress`, `PasswordHash`, and `UserId`. `PasswordPolicy` owns password-length policy shared by registration and password reset.

Domain has no dependency on Laravel, Eloquent, HTTP, SQL, Redis, queues, mail transports, or the service container.

### Infrastructure

Infrastructure implements Application ports. Eloquent repositories map between persisted records and Domain aggregates. Hashing, mail, clock, token generation, and transaction implementations are also infrastructure adapters.

The Laravel `App\\Models\\User` remains the framework authentication/persistence model under the conventional `app/Models` path, separate from the Domain `User`. This follows the Exchange architecture contract: a conventional Eloquent model location is acceptable as long as Domain never imports it and only Infrastructure persistence code depends on it.

The Laravel service provider is the composition root for binding ports to concrete adapters. It is framework wiring, not a second business-service layer.

## Dependency direction

```text
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
```

Source dependencies point inward: Presentation depends on Application; Application depends on Domain and its own ports; Infrastructure depends inward to implement those ports.

## Read/write separation

The current endpoints are state-changing use cases and therefore use Commands and Actions. CQRS is logical, not a requirement for separate databases.

When read requirements grow, add a focused Query or read service under Application and a corresponding presentation representation. Prefer a focused Eloquent query or custom query builder for read-specific joins and projections. Introduce a dedicated Read Model or database replica only when requirements or measured performance justify it.

Do not create generic repositories for every table. Repository ports are for persisted aggregate roots; simple read queries do not need to be forced through a write repository.

## Complexity guardrails

- No Value Object per database column.
- No Repository per table.
- No generic CRUD repository or generic service layer.
- No Command + Handler + Action triplet for one use case.
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

CI is configured to run the Identity test suite against SQLite and PostgreSQL.
