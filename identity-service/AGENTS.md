# Identity Service Development Guide

This service uses Laravel 13 and PHP 8.3+.

## Architecture

Identity is a module-first Laravel service. Keep code for an Identity capability together under app/Modules/Identity:

- Presentation/Http: HTTP controllers, Form Requests, API Resources, and module routes.
- Application/Commands: immutable, typed input contracts for state-changing use cases.
- Application/Actions: execution/orchestration of Commands. This project uses Actions instead of adding a second CommandHandler class for each Command.
- Application/Queries: immutable, typed input contracts for independent read-only use cases; create this folder when the first real Query exists.
- Application/QueryHandlers: execute Queries and return a read-specific DTO/projection; create a handler per real Query, without a generic bus or mediator.
- Application/Contracts: ports required by Application from persistence, clocks, mail, hashing, ID/token generation, or transactions.
- Application/Exceptions and Enums: stable application outcomes and application-level concepts.
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
- Independent read-only use cases must not be routed through UserRepository just because it can load aggregates. Implement them as a specific Query + QueryHandler and a read-specific projection/DTO, backed by a focused Infrastructure read adapter/SQL query.
- The Query side is introduced only when its consumer, authorization rules, and output contract are known. Do not expose account data through an unauthenticated lookup endpoint.
- Read and write currently use the same PostgreSQL database and schema. No replica, synchronization mechanism, mediator, command bus, or event sourcing is needed by default.
- Do not add a generic Query bus or a Query DTO + Handler + Service triplet. One specific Query and one specific Handler are enough when a real read use case is added.

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
