# Exchange Frontend

A single React application for the Exchange platform. It lives at the repository root, independently of the Laravel microservices, and is intended to become the shared user interface for Identity, Wallet and Trading.

## Stack

- React 19
- Vite 8
- React Router 7
- Lucide icons
- Persian RTL interface

## Local development

Start the shared Platform Compose so the API Gateway is available at `http://127.0.0.1:8080`. Start Identity as its own service as well.

From the repository root:

```powershell
cd frontend
Copy-Item .env.example .env
npm install
npm run dev
```

Open `http://localhost:5173`.

The Vite dev server proxies `/api/*` requests to the local API Gateway. The frontend does not call Identity, Wallet or Trading containers directly.

## API boundary

The shared API client maps services to their Gateway prefixes:

- Identity: `/api/v1/identity`
- Wallet: `/api/v1/wallet`
- Trading: `/api/v1/trading`

Identity is integrated through the Gateway:

- `POST /api/v1/identity/login`
- `POST /api/v1/identity/register`
- `GET /api/v1/identity/me`
- `POST /api/v1/identity/logout`
- `POST /api/v1/identity/password/forgot`
- `POST /api/v1/identity/password/reset`

The access token is kept in `sessionStorage`, validated on application startup through `/me`, and revoked on logout. The browser never receives the server-to-server `IDENTITY_USERS_READ_TOKEN`.

Wallet and Trading navigation is included as an integration-ready UI, but those screens remain in a clearly labelled preparation state until their public APIs and authorization contracts are ready. No balances, orders or transaction history are fabricated. The current Sanctum token authenticates Identity only; the client does not send it to Wallet or Trading.

## Production configuration

Set `VITE_API_ORIGIN` to the public Gateway origin when building for a separate frontend host. Prefer serving the built `dist/` directory behind the same origin as the Gateway; this avoids introducing unnecessary CORS configuration.

The Vite development proxy is not included in a production build.

## Security notes

- Keep server-side administrative credentials out of all `VITE_*` environment variables. Vite variables are public and compiled into browser assets.
- The current Identity API issues Sanctum Bearer tokens. This frontend stores the token in session storage to limit persistence to the browser tab session. This is not equivalent to an HttpOnly cookie; prevent XSS and use a strict Content Security Policy when deploying.
- Wallet and Trading authentication must not be assumed until a shared issuer/JWKS or trusted identity propagation contract has been implemented.
