# Identity Service Development Guide

This service uses Laravel 13 and PHP 8.3+.

## Architecture

Identity is a module-first Laravel service. Keep code for an Identity capability together under app/Modules/Identity:

- Presentation/Http: HTTP controllers, Form Requests, API Resources, and module routes.
- Application/Commands: immutable, typed input contracts for state-changing use cases.
- Application/Actions: use-case orchestration. Each Action represents one state-changing business operation; do not add a parallel Service or Handler for the same work.
- Application/Contracts: ports required by Application from persistence, clocks, mail, hashing, ID/token generation, or transactions. UserRepository is the write port; UserReader is the read port.
- Application/Exceptions and Enums: stable application outcomes and application-level concepts.
- Domain: entities/aggregate roots, value objects, policies, and business invariants.
- Infrastructure: Laravel/Eloquent adapters implementing Application contracts. EloquentUserRepository handles writes; EloquentUserReader handles reads.

The direction of dependencies is inward:

Presentation -> Application -> Domain
Infrastructure -> Application contracts + Domain

Domain must not depend on Laravel, HTTP, Eloquent, SQL, queues, cache, or the service container. Application must not depend on HTTP Request/Response classes. Controllers remain thin adapters.

The conventional App\Models\User is the Laravel authentication/persistence model. Keep it separate from the Domain Identity\Domain\Entities\User; only Infrastructure persistence adapters may use the Eloquent model. The application service provider is the composition root that binds ports to adapters.

Do not create speculative modules such as Sessions, MFA, or Credentials until they have distinct use cases and business boundaries. Do not create empty Queries, ViewModels, Results, or other folders before a real requirement needs them.

## CQRS boundary

- Keep the write repository free of read methods: UserRepository exposes add/save only.
- Put aggregate lookups behind UserReader. Its current use is to reconstitute an aggregate when an existing state-changing use case must validate and change that aggregate.
- This is logical read/write separation over the same PostgreSQL database and schema; it does not require a separate connection, database, replica, mediator, command bus, or event sourcing.
- Add an Application Query and a read-specific DTO/projection only when an independent read-only use case and its authorization/consumer contract exist. Do not expose account data through an unauthenticated lookup endpoint.
- Do not introduce a Query DTO + Query Handler + Query Service triplet for one simple read use case.

## Working conventions

- Follow the Exchange architecture contracts in ../platform/docs/architecture/development-contracts.fa.md and ../platform/docs/architecture/domain-driven-design.md.
- Keep changes scoped to Identity unless an explicit cross-service contract requires otherwise.
- Use immutable Commands as input DTOs; exclude transport-only fields such as password confirmation.
- Put orchestration in one Application Action per state-changing use case. Put invariants and state transitions in Domain.
- Use Repository ports for persisted aggregate roots, not for every table. Use Eloquent query builders or focused query adapters for read paths when appropriate.
- CQRS is logical by default. Keep read and write responsibilities separate in code, but do not introduce a second database, mediator, command bus, or event sourcing without a demonstrated need.
- Add or update automated tests for behavior changes.
- Do not add development tools or dependencies unless the task requires them and the change is agreed upon.
- Use the repository's existing Composer scripts for setup and testing.

## Local verification

Run these commands from identity-service/ when the local PHP and Composer environment is available:

    composer install
    php artisan test
