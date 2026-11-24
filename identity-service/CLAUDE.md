# Identity Service Coding Instructions

Use the current project conventions documented in [AGENTS.md](AGENTS.md). The active internal architecture is Service-Based Laravel:

    Route -> Controller -> Form Request -> Service -> Eloquent / DB

Keep controllers thin, use cohesive Services, validate HTTP inputs in Form Requests, and whitelist API output through Resources. Do not add Domain/Application/Infrastructure layers, Repository abstractions, Command/Query Handler classes, DTOs, or Interfaces by default.

## Authentication behavior

- Registration creates an active account immediately; do not require email verification for login.
- Login uses Laravel Sanctum personal access tokens. Store only their hash in the Sanctum table and always set an expiry.
- Suspended and closed accounts cannot authenticate; legacy pending accounts remain eligible during the optional-verification period.
- The raw token is returned once by login. It must not be logged or persisted in Postman environments shared publicly.
- Logout revokes only the current token; password reset revokes every token for that user.
- Sanctum tokens currently authenticate Identity only. Do not treat them as JWTs or assume Wallet/Trading/Gateway validate them until the inter-service contract is implemented.

Do not install Laravel Boost, new Composer packages, or development tools unless the user explicitly requests them. Preserve the security and public API rules in AGENTS.md and the platform architecture documentation.

Run the existing test suite with `php artisan test` when the local PHP environment is available. Never claim a test passed unless it was actually executed and passed.
