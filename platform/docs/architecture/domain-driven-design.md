# Domain-Driven Design Architecture Guidelines

**Status:** Accepted for the current Exchange codebase  
**Scope:** Identity, Wallet, Trading and their integration boundaries

This document records the practical DDD and Clean/Hexagonal Architecture rules used in Exchange. It is a set of design constraints, not a requirement to create every pattern or directory in every service.

## 1. Architectural stance

Exchange uses a pragmatic combination of:

- **Strategic DDD** to define bounded contexts and ownership.
- **Module-first organization** inside each Laravel service.
- **Clean / Hexagonal Architecture** to keep domain and application code independent from delivery and persistence frameworks.
- **Tactical DDD** where business rules and transactional invariants justify entities, value objects, aggregates and repositories.

Do not apply tactical DDD ceremony to simple CRUD behavior. The complexity of the business capability, not the number of tables or classes, determines how rich the model needs to be.

## 2. Bounded contexts own their models

Each service is a bounded context with its own ubiquitous language and model:

- **Identity** owns credentials, authentication identity, account security and identity lifecycle.
- **Wallet** owns financial accounts, ledger entries, holds, transfers and balance rules.
- **Trading** owns markets, orders, executions and trading rules.

A concept with the same name in two services is not automatically the same model. Other services refer to an Identity user by its stable identifier; they do not import the Identity domain entity, share its Eloquent model, or query its database.

Never use a cross-service foreign key or direct database access as an integration mechanism. Use APIs or versioned integration events.

## 3. Layers and dependency direction

The preferred dependency direction is inward:

    HTTP / Console / Queue
            |
            v
    Application (Commands, Use Cases, Results, Ports)
            |
            v
    Domain (Aggregates, Entities, Value Objects, Policies)
            ^
            |
    Infrastructure (Eloquent, SQL, Laravel adapters)
            |
            +---- implements Application ports

### Domain

- Contains business concepts, invariants and state transitions.
- Must not depend on Laravel, Eloquent, HTTP, queues, a database driver or the service container.
- Contains no request objects, ORM models or persistence annotations.
- Entities and aggregate roots protect their own invariants through behavior.
- Value Objects are immutable and validate the meaning they represent. Do not create a Value Object for every primitive property.
- A Domain Service is appropriate only when a domain rule does not naturally belong to one Entity or Value Object.

### Application

- Implements use cases and coordinates work.
- Contains typed, immutable command inputs and explicit result DTOs when crossing the use-case boundary.
- Depends on Domain types and ports (interfaces), not concrete infrastructure.
- Orchestrates repositories, policies, clocks or other ports as needed.
- Does not own business invariants or duplicate domain behavior.
- Does not expose HTTP Request/Response objects.

### Infrastructure

- Implements Application ports using Laravel, Eloquent, PostgreSQL, Redis, brokers and external systems.
- Owns ORM-specific models, persistence mappings and database-specific exception translation.
- Converts persisted records to and from Domain objects.
- Infrastructure may depend inward on Application contracts and Domain types; the reverse dependency is prohibited.

### Presentation

Controllers, Form Requests, API Resources and Console commands are delivery adapters. They translate transport input into Application Commands and translate Application Results or known failures into transport responses.

## 4. Commands and results

A Command is the input contract for one use case.

- Use a dedicated immutable Command for each state-changing use case.
- Use explicit typed properties; do not pass an unstructured associative array as the Application contract.
- A Command can have many properties. Group properties into nested data objects only when the group is a real, cohesive concept or independently reused contract.
- Do not create a second DTO merely to wrap one Command or reduce its constructor to one argument.
- Do not copy every database column into a Command. Include only data required by the operation.
- Exclude transport-only fields such as password confirmation unless the use case itself genuinely needs them.
- Return a Result DTO when the caller should not know about or depend on a Domain Entity.
- Do not add a mediator or command bus until dispatch, cross-cutting behaviors, or multiple transports provide a concrete benefit.

## 5. Aggregate design and consistency

An Aggregate is a transactional consistency boundary, not a convenient grouping of related tables.

- Every Aggregate has exactly one root.
- A root may be a single Entity; child Entities are not mandatory.
- Keep aggregates small and include only state that must be consistent within one transaction.
- Enforce invariants through the root. Do not update child entities through independent repositories.
- Reference another Aggregate by its identifier, not by a live object graph.
- Model eventual consistency explicitly when a process spans multiple aggregates.
- Do not create one aggregate per table or one repository per table.

Repository interfaces belong to Application (or the inward-facing Domain contract when justified by the model); implementations belong to Infrastructure. Create a repository for a persisted Aggregate Root, not for every Entity or table. Keep interfaces specific to actual use-case needs; avoid generic repositories that expose ORM query APIs.

## 6. Persistence, uniqueness and transactions

The database remains the final authority for constraints that must hold under concurrency.

An Application-level exists query may improve feedback, but it is not a concurrency guarantee. Use a database unique constraint and translate the specific constraint violation into a stable Application error. Do not catch every database exception and misreport it as a business conflict.

Use a transaction when a use case performs multiple writes that must commit or roll back together. Do not introduce a generic Unit of Work abstraction around a single atomic insert. When database state and an outgoing integration event must be committed reliably, use a transactional outbox; publish only after the originating transaction commits.

## 7. Domain events, integration events and CQRS

- A Domain Event describes a meaningful business fact, not a row insert or technical callback.
- Domain Events coordinate behavior within a bounded context.
- Integration Events are versioned contracts published across service boundaries.
- Do not publish an Integration Event before the originating database transaction commits.
- Use an outbox when reliable event publication is required.
- Do not adopt CQRS by default. Introduce separate read models or query services only when read requirements, performance, or model divergence justify them.

## 8. Validation and error mapping

- Form Requests validate transport concerns and provide useful HTTP feedback.
- Domain invariants must also be enforced when a use case is called outside HTTP.
- Application errors describe use-case outcomes; Domain exceptions describe violated domain rules.
- Infrastructure translates technology-specific failures into stable inward-facing errors.
- Controllers map known Application/Domain failures to HTTP status codes. Domain and Application code must not throw Laravel HTTP exceptions.

## 9. Testing rules

| Area | Test style | Database / Laravel |
|---|---|---|
| Domain | Unit tests for invariants, value objects and transitions | No Laravel or database |
| Application | Unit tests with mocked/fake ports | No database; avoid booting Laravel |
| Infrastructure | Integration tests for mapping, SQL constraints and adapters | Real supported database driver where practical |
| Presentation | Feature tests for HTTP validation, status codes and serialization | Laravel test application |

SQLite feature tests are useful for fast feedback, but PostgreSQL-specific constraint handling and behavior must also be covered by PostgreSQL integration tests or CI before relying on it in production.

## 10. Current Identity decisions

The registration vertical slice follows these rules:

- User is the Identity Domain Aggregate Root; currently it is a single-entity aggregate.
- RegisterUserCommand is the typed Application input.
- RegisterUserResult is the explicit output boundary; HTTP does not receive the Domain Entity.
- PasswordPolicy protects the minimum password-length rule even when registration is invoked outside HTTP. Form Request validation mirrors it for early feedback.
- UserId validates the ULID format required by the current persistence schema without depending on Laravel.
- UserRepository persists the aggregate. The database unique index is the authority for email uniqueness; the Eloquent adapter translates only that specific violation.
- UserModel is an Eloquent authentication/persistence model inside Infrastructure, not the Domain User.
- There is one aggregate write during registration, so no generic transaction manager or Unit of Work is introduced.
- No User lifecycle behavior (verification, suspension, recovery, password rotation) is invented before its rules are specified.

Wallet and Trading must use the same dependency and boundary principles, but their aggregates must be discovered from their own invariants and business operations. Do not copy Identity's User shape or pre-create speculative aggregate classes.

## 11. References

1. Microsoft, [Use Tactical DDD to Design Microservices](https://learn.microsoft.com/en-us/azure/architecture/microservices/model/tactical-domain-driven-design) — entities, value objects, aggregate consistency boundaries, small aggregates, IDs and services.
2. Microsoft, [Use Domain Analysis to Model Microservices](https://learn.microsoft.com/en-us/azure/architecture/microservices/model/domain-analysis) — bounded contexts, business capabilities, cohesion and ubiquitous language.
3. Microsoft, [Designing a DDD-oriented Microservice](https://learn.microsoft.com/en-us/dotnet/architecture/microservices/microservice-ddd-cqrs-patterns/ddd-oriented-microservice) — when rich DDD is justified and when simple CRUD is enough.
4. Microsoft, [Implementing the Microservice Application Layer](https://learn.microsoft.com/en-us/dotnet/architecture/microservices/microservice-ddd-cqrs-patterns/microservice-application-layer-implementation-web-api) — command handling, thin application orchestration and keeping domain rules in the model.
5. Microsoft, [Designing the Infrastructure Persistence Layer](https://learn.microsoft.com/en-us/dotnet/architecture/microservices/microservice-ddd-cqrs-patterns/infrastructure-persistence-layer-design) — repositories and persistence boundaries.
6. Vaughn Vernon, [Effective Aggregate Design](https://www.dddcommunity.org/library/vernon_2011/) — consistency boundaries, avoiding large aggregates and aggregate design trade-offs.
7. Martin Fowler, [Anemic Domain Model](https://martinfowler.com/bliki/AnemicDomainModel.html) — distinguishing behavioral domain models from data-only objects.
8. Laravel, [Eloquent ORM](https://laravel.com/docs/eloquent) — framework persistence model behavior and conventions.
