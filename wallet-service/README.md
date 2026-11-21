# wallet-service

Laravel 13 application skeleton for the Exchange wallet-service service.

## Run with Docker

Create the shared application network once if it does not already exist:

```bash
docker network create exchange-network
```

Copy the environment template and generate an application key:

```bash
cp .env.example .env
docker compose run --rm app php artisan key:generate --show
```

Copy the generated key into the `APP_KEY` value in `.env`, then start the service:

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
```

The application is available at `http://localhost:8003`.

PostgreSQL is private to this service and is not published on the host. The application joins the shared `exchange-network` so the API Gateway and other platform components can reach it by container name. The database uses a separate internal Docker network.

Override local credentials in `.env` before starting. Never use the sample credentials in production.

This is an uncustomized Laravel starting point. No domain modules, business rules, authentication flows, or service APIs have been added.
