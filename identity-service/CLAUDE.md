# Identity Service Coding Instructions

Use the current project conventions documented in [AGENTS.md](AGENTS.md). The active internal architecture is Service-Based Laravel:

    Route -> Controller -> Form Request -> Service -> Eloquent / DB

Keep controllers thin, use cohesive Services, validate HTTP inputs in Form Requests, and whitelist API output through Resources. Do not add Domain/Application/Infrastructure layers, Repository abstractions, Command/Query Handler classes, DTOs, or Interfaces by default.

Do not install Laravel Boost, new Composer packages, or development tools unless the user explicitly requests them. Preserve the security and public API rules in AGENTS.md and the platform architecture documentation.

Run the existing test suite with `php artisan test` when the local PHP environment is available. Never claim a test passed unless it was actually executed and passed.
