# identity-service

Laravel 13 application skeleton for the Exchange identity-service service.

## Run with Docker

Create the shared application network once (if it does not exist):

```bash
docker network create exchange-network
```

Start the service from this directory:

```bash
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

The application is available at `http://localhost:8001`.

The PostgreSQL database is private to this service and is not exposed on the host. The application joins the shared `exchange-network` so the API Gateway can reach it by its service container name. The database uses a separate internal Docker network.

Copy `.env.example` to `.env` when running Laravel commands outside the container. For Compose, database connection settings are supplied directly by the Compose file. Override `DB_PASSWORD` and `APP_KEY` through the environment for non-local environments.

This is an uncustomized Laravel starting point. No domain modules, business rules, authentication flows, or service APIs have been added.
