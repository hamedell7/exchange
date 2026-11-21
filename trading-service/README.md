# Trading Service

Laravel 13 application for the Exchange trading microservice.

## Local Docker development (PowerShell, from the repository root)

Create the environment file once:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
if (-not (Test-Path .\trading-service\.env)) {
    Copy-Item .\trading-service\.env.example .\trading-service\.env
}
```

Start shared infrastructure, which creates the external network `exchange-network`:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
```

Build and start Trading, generate its key, and run migrations:

```powershell
docker compose --env-file trading-service/.env -f trading-service/compose.yaml up -d --build
docker compose --env-file trading-service/.env -f trading-service/compose.yaml exec app php artisan key:generate
docker compose --env-file trading-service/.env -f trading-service/compose.yaml exec app php artisan migrate
```

The API is available at [http://localhost:8002](http://localhost:8002).

## Development volume model

- `trading-service/.` is bind-mounted to `/var/www/html`, so host source changes appear immediately in the container.
- `trading_vendor` is a named Docker volume for Linux Composer dependencies; it is mounted inside the source tree at `vendor`.
- `trading_postgres_data` persists Trading's PostgreSQL data independently.

The generated `APP_KEY` is saved to `trading-service/.env` through the bind mount. Compose does not set an empty `APP_KEY` that could override the file value.

After changing `composer.lock`, run:

```powershell
docker compose --env-file trading-service/.env -f trading-service/compose.yaml exec app composer install
```

Stop without deleting persistent data:

```powershell
docker compose --env-file trading-service/.env -f trading-service/compose.yaml down
```

Avoid `down -v` unless deleting the database and dependency volumes is intentional. This bind-mounted configuration is for development; production should run the built image without mounting the source tree.
