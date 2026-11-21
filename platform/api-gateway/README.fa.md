# API Gateway

Nginx edge gateway for the Exchange platform. This component is intentionally stateless: it does not own users, balances, orders, permissions, or business workflows.

## Current routes

| Public path | Upstream |
|---|---|
| `/api/v1/identity/*` | Identity service |
| `/api/v1/trading/*` | Trading service |
| `/api/v1/wallet/*` | Wallet service |
| `/healthz` | Gateway process health |

The original URI is preserved when proxying. The three service applications must expose matching route prefixes, or the prefixes must be changed here once their API contracts are finalized.

## Included edge protections

- Per-client request rate limiting and concurrent connection limiting.
- Request body size and client timeout limits.
- Explicit upstream connect/read/write timeouts.
- Request ID generated at the gateway and forwarded to the upstream.
- Basic security response headers and hidden Nginx version.
- A local process health endpoint.

Rate limits are initial development defaults, not production capacity decisions. Tune them using load tests and the expected traffic profile.

## Build

Run from the repository root:

```powershell
docker build -t exchange-api-gateway:local ./platform/api-gateway
```

## Run

Copy `.env.example` to `.env` and set the three upstream addresses. The gateway container must share a Docker network with the upstream containers, or the configured hostnames must be reachable from inside the gateway container.

```powershell
Copy-Item platform/api-gateway/.env.example platform/api-gateway/.env

docker run --rm --name exchange-api-gateway --env-file platform/api-gateway/.env -p 127.0.0.1:8080:8080 exchange-api-gateway:local
```

Check the gateway:

```powershell
Invoke-WebRequest http://127.0.0.1:8080/healthz
```

The health endpoint only confirms that Nginx is running; it does not prove that all upstream services are healthy.

## Configuration

All upstream values are required at container startup:

- `IDENTITY_SERVICE_HOST`, `IDENTITY_SERVICE_PORT`
- `TRADING_SERVICE_HOST`, `TRADING_SERVICE_PORT`
- `WALLET_SERVICE_HOST`, `WALLET_SERVICE_PORT`

See `.env.example` for local placeholders. Never commit real credentials or production hostnames.

## Security boundaries

- TLS must terminate at a trusted load balancer/ingress in production, or be configured directly for the gateway before exposing it publicly.
- Do not trust client-supplied identity headers. This gateway does not synthesize authenticated user identity.
- The gateway does not replace JWT signature/issuer/audience validation or authorization in the owning services.
- Services must enforce object-level and action-level authorization themselves.
- Only trust forwarded client IP/protocol headers from a known upstream proxy. Configure the real-IP module and trusted proxy ranges explicitly if another proxy sits in front.
- Keep the gateway and services on private networks; do not publish service ports publicly.

## Deliberate non-goals for this first slice

JWT/OIDC validation, CORS policy, TLS certificates, distributed rate limiting, circuit breaking, retries, request/response transformation, and service-specific API documentation are not enabled yet. They require the identity issuer/JWKS contract, frontend origin policy, deployment topology, and finalized service API contracts. In particular, the gateway does not retry financial write requests because retries can duplicate non-idempotent operations.
