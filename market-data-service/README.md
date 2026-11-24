# Market Data Service

Laravel 13 microservice that owns the public global asset catalog and cached market quotes used by the Exchange market homepage.

## Responsibilities

- Imports active cryptocurrencies ordered by CoinMarketCap market-cap rank.
- Stores canonical provider IDs, names, symbols, slugs, rank and static metadata (logo, description, website, tags and platform).
- Synchronizes the latest asset quotes in USDT from CoinMarketCap.
- Synchronizes the USDT/Rial market rate from Nobitex and converts it to Toman (Rial / 10).
- Stores current quotes and the USDT/Toman exchange rate in PostgreSQL.
- Exposes a public, read-only REST endpoint with USDT and Toman prices and 24-hour changes.
- Refreshes the top 250 asset prices every five minutes using a dedicated Laravel scheduler container.
- Does not implement WebSocket; clients currently use ordinary HTTP requests.

## Local Docker development (PowerShell, from repository root)

Create the service environment file once:

    if (-not (Test-Path .\platform\infrastructure\.env)) {
        Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
    }
    if (-not (Test-Path .\market-data-service\.env)) {
        Copy-Item .\market-data-service\.env.example .\market-data-service\.env
    }

Set your CoinMarketCap key in market-data-service/.env:

    CMC_API_KEY=your_actual_api_key

Do not commit the real key.

Start shared infrastructure and create the dedicated database and role:

    docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
    platform\infrastructure\scripts\bootstrap-databases.cmd

Build and start the application and scheduler:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build app scheduler
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan key:generate
    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force

The scheduler container runs php artisan schedule:work. It invokes market:sync-prices --limit=250 every five minutes. The market-data service must be connected to the shared exchange-network, and the PostgreSQL database and role must exist before running migrations.

## One-time asset import

Populate or refresh the top asset catalog manually:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250

The command makes two CoinMarketCap requests:
1. /v1/cryptocurrency/map for ranked active asset identifiers.
2. /v2/cryptocurrency/info for static metadata and logos.

Run this command again manually when you want to refresh the catalog. --dry-run fetches and validates provider responses without writing to the database. --start can be used for another page; use --start=1 when replacing the current top-N ranking.

## Market price synchronization

Run a one-time price refresh:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-prices --limit=250

Validate provider connectivity without writing to PostgreSQL:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-prices --limit=250 --dry-run

CoinMarketCap returns up to 250 cryptocurrency quotes in one request and the quote endpoint charges one call credit per 250 returned assets when using one quote currency. The scheduler therefore runs every five minutes by default: approximately 8,640 price-call credits in a 30-day month, leaving headroom under the Basic plan's 15,000 monthly credits for manual asset refreshes and other API calls. Confirm your account's current quota and usage in the CoinMarketCap Developer Portal before increasing the asset count or polling frequency.

Nobitex GET /market/stats?srcCurrency=usdt&dstCurrency=rls returns the USDT/Rial market's latest price. The service divides this value by 10 to store a USDT/Toman rate. The public market API calculates each Toman quote as:

    price_toman = price_usdt * usdt_toman_rate

The price sync is atomic: if either provider fails or returns incomplete data, the current quote batch is not partially persisted. The previous valid prices remain available until a successful sync.

## Public API

GET /api/v1/market/assets?per_page=50&page=1

Optional search:

GET /api/v1/market/assets?search=bit&page=1&per_page=25

Each asset includes:
- price_usdt
- price_toman
- percent_change_24h
- price_updated_at

The response also includes market_data.usdt_toman_rate and market_data.rate_updated_at. If prices have not been synchronized yet, price fields are null; import assets and run the price sync command to populate them. The endpoint is public and does not require an Identity token.

## Local addresses

- API through Gateway: http://127.0.0.1:8080/api/v1/market/assets
- Direct service access for development: http://127.0.0.1:8003/api/v1/market/assets
- Adminer: http://127.0.0.1:8081

For Adminer, choose PostgreSQL, server postgres, and connect with the appropriate database role. For Market Data use database market_data_db, username market_data_app, and the password in the local environment files.

Inspect scheduler activity:

    docker logs --tail 100 market-data-scheduler

## Development volume model

- Source is bind-mounted to /var/www/html.
- Composer dependencies live in the service-specific market_data_vendor named volume.
- PostgreSQL is owned by platform/infrastructure; this service only owns its own market_data_db database and market_data_app role.
