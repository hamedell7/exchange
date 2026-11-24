# Identity registration architecture comparison

**Status: Superseded by the Service-Based architecture decision (2026-11-24).**

This file was a learning comparison between a Laravel Service/Repository implementation and a more layered pragmatic DDD implementation. It no longer describes the production architecture or the current registration implementation.

The current Exchange decision is to use Laravel's conventional Service-Based structure:

    Route -> Controller -> Form Request -> Service -> Eloquent / DB

Repository abstractions, Domain/Application/Infrastructure layers, Commands, Handlers and Value Objects are not introduced by default. They may be reconsidered only for a concrete capability whose complexity justifies them.

The current implementation guidance is maintained in:

- [Service-Based architecture](../../platform/docs/architecture/service-based-architecture.md)
- [Exchange development contracts](../../platform/docs/architecture/development-contracts.fa.md)
- [Identity service architecture](../../identity-service/docs/architecture.md)

The earlier comparison remains in Git history for historical learning purposes; its recommendation to use moderate DDD in Identity and stronger tactical DDD by default in Wallet and Trading is no longer the project's baseline.
