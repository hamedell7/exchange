# Market Data Service

Laravel 13 microservice that owns the public global asset catalog used by the Exchange market homepage.

## Scope of this first increment

- Imports the top active cryptocurrencies ordered by CoinMarketCap market-cap rank.
- Stores canonical provider IDs, names, symbols, slugs, rank and static metadata (logo, description, website, tags and platform).
- Exposes a public, read-only REST endpoint for the asset catalog.
- Does not store ticker prices, convert USDT/IRT, schedule recurring market polling, or implement WebSocket. Those are separate next steps.

## Local Docker development (PowerShell, from repository root)

Create the service environment file once:

```powershell
if (-not (Test-Path .\platform\infrastructure\.env)) {
    Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
}
if (-not (Test-Path .\market-data-service\.env)) {
    Copy-Item .\market-data-service\.env.example .\market-data-service\.env
}
```

Set your CoinMarketCap key in market-data-service/.env:

```dotenv
CMC_API_KEY=your_actual_api_key
```

Do not commit the real key.

Start shared infrastructure and create the dedicated database and role:

```powershell
docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
platform\infrastructure\scripts\bootstrap-databases.cmd
```

Build and start the service:

```powershell
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan key:generate
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force
```

## One-time asset import

Run this command manually when you want to populate or refresh the top asset catalog:

```powershell
docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250
```

The command makes two CoinMarketCap requests:
1. /v1/cryptocurrency/map for ranked active asset identifiers. This endpoint currently consumes no API credits.
2. /v2/cryptocurrency/info for static metadata and logos. Up to 250 assets consume one API credit.

The command is intentionally not scheduled. Run it again manually when you want to refresh the catalog. --dry-run fetches and validates both responses without writing to the database. --start can be used for another page; use --start=1 when replacing the current top-N ranking.

## Public API

GET /api/v1/market/assets?per_page=50&page=1

Optional search:

GET /api/v1/market/assets?search=bit&page=1&per_page=25

Only assets with a current imported market-cap rank are returned, ordered by rank. The endpoint is public and does not require an Identity token.

## Local addresses

- API through Gateway: http://127.0.0.1:8080/api/v1/market/assets
- Direct service access for development: http://127.0.0.1:8003/api/v1/market/assets
- Adminer: http://127.0.0.1:8081

For Adminer, choose PostgreSQL, server postgres, and connect with the appropriate database role. For Market Data use database market_data_db, username market_data_app, and the password in the local environment files.

## Development volume model

- Source is bind-mounted to /var/www/html.
- Composer dependencies live in the service-specific market_data_vendor named volume.
- PostgreSQL is owned by platform/infrastructure; this service only owns its own market_data_db database and market_data_app role.
