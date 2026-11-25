# Market Data Service

Laravel 13 microservice that owns the public asset catalog and cached market quotes used by the Exchange market homepage.

## Responsibilities

- Imports ranked asset identifiers and static metadata from CoinMarketCap REST.
- Uses Nobitex public market statistics as the source for asset quotes.
- Prefers a direct \`ASSET-USDT\` market when it exists.
- If a direct USDT market is unavailable, derives the USDT price from the asset's Rial market divided by the Nobitex USDT/Rial market price.
- Converts USDT quotes to Toman using the live USDT/Rial market rate (Rial / 10).
- Stores quotes and the USDT/Toman conversion rate in PostgreSQL.
- Exposes a public, read-only REST endpoint with USDT and Toman prices and 24-hour changes.
- Refreshes a REST snapshot every five minutes.
- Runs a long-lived Nobitex WebSocket consumer that subscribes once to \`public:market-stats-all\`, persists changed prices, and broadcasts them through Laravel Reverb.

CoinMarketCap is retained for the asset catalog and static metadata only. Binance is not used; the public server endpoint returned HTTP 451 for this deployment region.

## Local Docker setup (PowerShell, from repository root)

Create the environment files if they do not exist:

    if (-not (Test-Path .\\platform\\infrastructure\\.env)) {
        Copy-Item .\\platform\\infrastructure\\.env.example .\\platform\\infrastructure\\.env
    }
    if (-not (Test-Path .\\market-data-service\\.env)) {
        Copy-Item .\\market-data-service\\.env.example .\\market-data-service\\.env
    }

Set \`CMC_API_KEY\` in \`market-data-service/.env\` only if you need to import or refresh the static asset catalog. No Binance or Nobitex API key is needed for public market prices.

Set the Reverb app credentials in \`market-data-service/.env\`:

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
    platform\\infrastructure\\scripts\\bootstrap-databases.cmd

Build and start the API container:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build app

Run migrations:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan migrate --force

For a fresh database, import the asset catalog before starting the streamer. The Reverb server runs inside the app container. Start it in a separate terminal (or use \`exec -d\`):

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec -d app php artisan reverb:start --host=0.0.0.0 --port=8080

After the database and catalog are ready, start the scheduler and streamer:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml up -d --build scheduler streamer

## Asset catalog import

CoinMarketCap is used only for the canonical asset catalog and static metadata:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-assets --limit=250

This is a manual catalog operation. It requires \`CMC_API_KEY\`. Run it when you want to refresh names, symbols, market-cap rank, logos and metadata.

## Nobitex price snapshot

Run a one-time price refresh:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-prices --limit=250

Validate the provider response without writing to PostgreSQL:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml exec app php artisan market:sync-prices --limit=250 --dry-run

The snapshot uses one public request:

    GET https://apiv2.nobitex.ir/market/stats

Omitting \`srcCurrency\` and \`dstCurrency\` returns the public market statistics map. The documented rate limit for this endpoint is 20 requests per minute; the five-minute scheduler and one initial streamer snapshot stay below that limit.

Price mapping rules:

1. Use \`symbol-usdt\` when the asset has a direct USDT market on Nobitex.
2. Otherwise use \`symbol-rls\` (or the WebSocket \`symbol-irt\` alias) and calculate \`price_usdt = asset_latest_riel / usdt_latest_riel\`.
3. Convert the USDT price to Toman using \`usdt_latest_riel / 10\`.
4. For Rial-quoted markets, calculate the 24-hour USDT change against both the asset's and USDT's 24-hour opening prices when available. If those are missing, derive it from the two reported day-change values.
5. Skip duplicate asset symbols and assets with no matching active Nobitex market. Remove any old quote for those selected assets instead of displaying a stale price from another provider.

The quote schema is reused; no database migration is needed.

## Continuous live stream

The \`streamer\` Compose service starts this command automatically:

    php artisan market:stream-nobitex --limit=250

The command:

1. Fetches one initial Nobitex all-market REST snapshot.
2. Connects to \`wss://ws.nobitex.ir/connection/websocket\`.
3. Opens one unauthenticated Centrifugo connection and subscribes to \`public:market-stats-all\`.
4. Handles Centrifugo connect/subscribe responses, application ping/pong, WebSocket ping/pong and reconnects with exponential backoff.
5. Maps only selected catalog assets, writes changed quote rows to PostgreSQL, and publishes changed prices through Reverb.

Nobitex documents this public WebSocket channel as not requiring a token. The documented limits are 100 concurrent connections per IP and 450 channel subscriptions per connection. We use one connection and one channel subscription.

The Reverb event is sent on public channel \`market.prices\` with event \`market.prices.updated\`.

Inspect the streamer:

    docker logs --tail 100 market-data-streamer

Restart it:

    docker compose --env-file market-data-service/.env -f market-data-service/compose.yaml restart streamer

## Frontend live connection

Copy these settings into \`frontend/.env\` (the Reverb app key is public; do not expose the secret):

    VITE_REVERB_HOST=127.0.0.1
    VITE_REVERB_PORT=8082
    VITE_REVERB_SCHEME=ws
    VITE_REVERB_APP_KEY=your_reverb_app_key

Restart the Vite dev server after changing its environment file. In production, use the public WSS hostname and TLS port instead of the local values.

The market page loads an initial REST snapshot and then applies live Reverb updates to visible rows. Its existing 60-second REST refresh remains as a fallback while the live channel is disconnected.

## Public API

    GET /api/v1/market/assets?per_page=50&page=1
    GET /api/v1/market/assets?search=bit&page=1&per_page=25

Each asset includes:

- \`price_usdt\`
- \`price_toman\`
- \`percent_change_24h\`
- \`price_updated_at\`

The response also includes \`market_data.usdt_toman_rate\` and \`market_data.rate_updated_at\`. The endpoint is public and does not require an Identity token.

## Local addresses

- API through Gateway: http://127.0.0.1:8080/api/v1/market/assets
- Direct service access: http://127.0.0.1:8003/api/v1/market/assets
- Reverb WebSocket: ws://127.0.0.1:8082
- Adminer: http://127.0.0.1:8081

For Adminer, choose PostgreSQL, server \`postgres\`, database \`market_data_db\`, username \`market_data_app\`, and the password in your local environment files.
