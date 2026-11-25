# Market Data Service

Laravel 13 microservice that owns the public asset catalog and cached market quotes used by the Exchange market homepage.

## Responsibilities

- Imports the ranked asset catalog and static metadata from CoinMarketCap REST.
- Uses Binance public Spot market data as the source of USDT-denominated crypto prices.
- Uses Nobitex to obtain the USDT/Rial rate and converts it to Toman (Rial / 10).
- Stores the current quotes and USDT/Toman conversion rate in PostgreSQL.
- Exposes a public, read-only REST endpoint with USDT and Toman prices and 24-hour changes.
- Refreshes a REST price snapshot every five minutes.
- Runs a separate long-lived Binance WebSocket consumer that persists price changes and broadcasts them through Laravel Reverb to the browser.

CoinMarketCap is retained for the asset catalog only. Its paid WebSocket stream is not used for price updates.

## Local Docker setup (PowerShell, from repository root)

Create the service environment file if it does not exist:

    if (-not (Test-Path .\platform\infrastructure\.env)) {
        Copy-Item .\platform\infrastructure\.env.example .\platform\infrastructure\.env
    }
    if (-not (Test-Path .\market-data-service\.env)) {
        Copy-Item .\market-data-service\.env.example .\market-data-service\.env
    }

Set `CMC_API_KEY` in `market-data-service/.env` for the one-time asset-catalog import. No Binance API key is needed.

Set the Reverb app credentials in `market-data-service/.env`:

    BROADCAST_CONNECTION=reverb
    REVERB_APP_ID=your_app_id
    REVERB_APP_KEY=your_app_key
    REVERB_APP_SECRET=your_app_secret
    REVERB_HOST=app
    REVERB_PORT=8080
    REVERB_SCHEME=http
    REVERB_SERVER_HOST=0.0.0.0
    REVERB_SERVER_PORT=8080

Do not commit real credentials.

Start shared infrastructure and create the Market Data database/role:

    docker compose --env-file platform/infrastructure/.env -f platform/infrastructure/compose.yaml up -d
    platform\infrastructure\scripts\bootstrap-databases.cmd

Build and start the API container:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build app

Run migrations:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force

For a fresh database, import the asset catalog as described below before starting the long-running streamer. The Reverb server runs inside the app container. Start it in a separate terminal (or use `exec -d`):

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec -d app php artisan reverb:start --host=0.0.0.0 --port=8080

After the database and catalog are ready, start the scheduler and streamer:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build scheduler streamer

The streamer publishes to Reverb when the server is listening. If Reverb is temporarily unavailable, quotes are still persisted; subsequent ticker updates are broadcast after Reverb comes back.

## Asset catalog import

CoinMarketCap is used only to create/refresh the canonical asset catalog and static metadata:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250

This is a manual catalog operation. It requires `CMC_API_KEY`. Run it again when you want to refresh names, symbols, rank, logos and metadata.

## Binance price snapshot

Run a one-time snapshot:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-prices --limit=250

Validate the provider responses without writing to PostgreSQL:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-prices --limit=250 --dry-run

Binance public Spot REST is used for the initial snapshot and the five-minute fallback schedule. The default market-data-only endpoints are:

- REST: `https://data-api.binance.vision`
- WebSocket: `wss://data-stream.binance.vision:443/ws/!ticker_1d@arr`

These public market-data endpoints do not require an API key. The selected stream is Binance's all-market rolling 24-hour ticker array. It publishes changed tickers at approximately one-second intervals; the consumer filters the stream to the selected assets. Binance is the source exchange, not a global aggregated price index.

The service maps an asset to Binance only when there is one unambiguous active Spot market with USDT as quote currency. Duplicate symbols and assets without an active Binance USDT Spot pair are skipped. Their previously stored USDT quote is removed for the selected synchronization batch so a stale CoinMarketCap price is not presented as a Binance price.

The Binance ticker provides last price, 24-hour percent change and 24-hour quote volume. It does not provide CoinMarketCap market cap, 1-hour change or 7-day change; those quote fields are left null rather than mixed with another provider's data.

## Continuous live stream

The `streamer` Compose service starts this long-running command automatically:

    php artisan market:stream-binance --limit=250

The command:

1. Fetches an initial Binance REST snapshot and the Nobitex USDT/Toman rate.
2. Resolves the active Binance USDT Spot pairs for the selected catalog assets.
3. Connects to Binance's public WebSocket stream, handles server ping/pong and planned shutdowns, and reconnects with exponential backoff.
4. Persists changed quotes in `market_asset_quotes`.
5. Broadcasts the updated prices on the public Reverb channel `market.prices`, event `market.prices.updated`.

No database migration or API key is required for this change. The existing quote schema is reused.

Inspect the streamer:

    docker logs --tail 100 market-data-streamer

Restart it:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml restart streamer

## Frontend live connection

Copy these settings into `frontend/.env` (the Reverb app key is public; do not expose the secret):

    VITE_REVERB_HOST=127.0.0.1
    VITE_REVERB_PORT=8082
    VITE_REVERB_SCHEME=ws
    VITE_REVERB_APP_KEY=your_reverb_app_key

Restart the Vite dev server after changing its environment file. In production, use the public WSS hostname and TLS port instead of the local values.

The market page initially loads the REST snapshot and then applies live Reverb updates to visible rows. Its existing 60-second REST refresh remains as a fallback while the live channel is disconnected.

## USDT/Toman conversion

Nobitex `GET /market/stats?srcCurrency=usdt&dstCurrency=rls` returns the USDT/Rial market's latest price. The service divides it by 10 to store a USDT/Toman rate.

    price_toman = price_usdt * usdt_toman_rate

The five-minute scheduler also refreshes this conversion rate. Each live market event includes the most recently stored conversion rate so the browser can recalculate Toman prices without a page reload.

## Public API

    GET /api/v1/market/assets?per_page=50&page=1
    GET /api/v1/market/assets?search=bit&page=1&per_page=25

Each asset includes:

- `price_usdt`
- `price_toman`
- `percent_change_24h`
- `price_updated_at`

The response also includes `market_data.usdt_toman_rate` and `market_data.rate_updated_at`. If prices have not been synchronized yet, price fields are null; import the asset catalog and run the snapshot command first. The endpoint is public and does not require an Identity token.

## Local addresses

- API through Gateway: http://127.0.0.1:8080/api/v1/market/assets
- Direct service access: http://127.0.0.1:8003/api/v1/market/assets
- Reverb WebSocket: ws://127.0.0.1:8082
- Adminer: http://127.0.0.1:8081

For Adminer, choose PostgreSQL, server `postgres`, database `market_data_db`, username `market_data_app`, and the password in your local environment files.

