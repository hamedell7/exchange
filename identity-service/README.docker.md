# Identity Service — Docker Development

This Compose stack is for local development. It keeps the service independently runnable while making source changes on the host immediately available inside the container.

## First-time setup (PowerShell, from the repository root)

Create local environment files once. Do not overwrite existing files:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
if (-not (Test-Path .\identity-service\.env)) {
    Copy-Item .\identity-service\.env.example .\identity-service\.env
}
```

Start the shared platform infrastructure first. It owns and creates the Docker network named `exchange-network`; do not create that network manually:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
```

Build and start Identity:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml up -d --build
docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan key:generate
docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app php artisan migrate
```

The generated `APP_KEY` is written to the host file `identity-service/.env` through the bind mount. Compose deliberately does not inject an empty `APP_KEY` environment variable, so Laravel can read the generated value from that file.

The application is available at [http://localhost:8001](http://localhost:8001).

## Volume layout

- The service directory is bind-mounted to `/var/www/html`. Source edits, `.env`, and local storage changes are visible on both sides.
- `identity_vendor` is a Docker-managed named volume mounted over `/var/www/html/vendor`. It keeps Linux Composer dependencies separate from the Windows host filesystem. Docker initializes a new empty volume from the image's existing `vendor` directory.
- `identity_postgres_data` persists this service's PostgreSQL data independently of the application source and dependencies.

When `composer.lock` changes, refresh the dependencies in the existing volume:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml exec app composer install
```

## Lifecycle and safety

Stop the service without deleting its database or Composer volume:

```powershell
docker compose --env-file identity-service/.env -f identity-service/compose.yaml down
```

Do not use `down -v` unless you intentionally want to delete the service's persistent volumes. The bind-mounted development setup is not intended for production deployments; production should run the immutable application image without a source bind mount.
