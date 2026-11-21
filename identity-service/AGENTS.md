# Identity Service Development Guide

This service uses Laravel 13 and PHP 8.3+.

## Working conventions

- Follow the existing project architecture and keep changes scoped to the Identity service.
- Keep domain code independent of Laravel, Eloquent, HTTP, and infrastructure concerns.
- Put use-case orchestration in the Application layer and framework-specific adapters in Infrastructure.
- Add or update automated tests for behavior changes.
- Do not add development tools or dependencies unless the task requires them and the change is agreed upon.
- Use the repository's existing Composer scripts for setup and testing.

## Local verification

Run these commands from `identity-service/` when the local PHP and Composer environment is available:

```sh
composer install
php artisan test
```
