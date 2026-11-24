# Wallet Service

Laravel 13 application for the Exchange wallet microservice.

## Local Docker development (PowerShell, from the repository root)

Create the environment file once:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
if (-not (Test-Path .\wallet-service\.env)) {
    Copy-Item .\wallet-service\.env.example .\wallet-service\.env
}
```

Start shared infrastructure, which creates the external network `exchange-network`:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
platform\infrastructure\scripts\bootstrap-databases.cmd
```

The bootstrap command creates the three isolated databases and login roles on the shared PostgreSQL server. Keep the WALLET_DB_PASSWORD in the infrastructure `.env` equal to `DB_PASSWORD` in `wallet-service/.env` (the example values already match).

Build and start Wallet, generate its key, and run migrations:

```powershell
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml up -d --build
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml exec app php artisan key:generate
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml exec app php artisan migrate
```

The API is available at [http://localhost:8003](http://localhost:8003).

## Development volume model

- `wallet-service/.` is bind-mounted to `/var/www/html`, so host source changes appear immediately in the container.
- `wallet_vendor` is a named Docker volume for Linux Composer dependencies; it is mounted inside the source tree at `vendor`.
- PostgreSQL is not part of this service Compose. Wallet connects to the shared `postgres` container on `exchange-network`, using its own `wallet_db` database and `wallet_app` role.

The generated `APP_KEY` is saved to `wallet-service/.env` through the bind mount. Compose does not set an empty `APP_KEY` that could override the file value.

After changing `composer.lock`, run:

```powershell
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml exec app composer install
```

Stop the service without deleting its Composer volume:

```powershell
docker compose --env-file wallet-service/.env -f wallet-service/compose.yaml down
```

Avoid `down -v` unless deleting the Composer volume is intentional. Shared PostgreSQL data is owned by `platform/infrastructure` and must be managed there. This bind-mounted configuration is for development; production should run the built image without mounting the source tree.
