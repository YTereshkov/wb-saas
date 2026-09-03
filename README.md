# SellerScope

SellerScope is a Laravel application for Wildberries seller analytics.

User documentation: [SellerScope user guide](docs/user-guide.md) (in Russian).

## Local development environment

Requirements: Docker with Docker Compose.

1. Create the local environment file:

    ```bash
    cp .env.example .env
    ```

2. Build the application image:

    ```bash
    docker compose build
    ```

3. Generate the local application key:

    ```bash
    docker compose run --rm app php artisan key:generate --force
    ```

4. Set `HORIZON_ALLOWED_EMAILS` to the comma-separated accounts that may open
   the internal queue dashboard, then start the application, PostgreSQL, Redis,
   Horizon, and scheduler:

    ```bash
    docker compose up -d --wait
    ```

5. Check service health and open the application:

    ```bash
    docker compose ps
    ```

    The application is available at <http://localhost:8000> by default. Set
    `APP_PORT` in `.env` to use another host port. The protected Horizon
    dashboard is available at <http://localhost:8000/horizon>.

6. Prepare the deterministic demo cabinet:

    ```bash
    docker compose exec app php artisan db:seed --force
    ```

    Sign in with `demo@sellerscope.local` and `SellerScopeDemo1!`. The seed is
    repeatable and imports the canonical June/July 2026 demo dataset through the
    same synchronization and aggregation pipeline used by the application.

The `app` service applies pending Laravel migrations before starting the local
web server. PostgreSQL and Redis data are stored in named Docker volumes.
Rebuild the application image after changing PHP or frontend source files:

```bash
docker compose up -d --build --wait
```

Useful commands:

```bash
docker compose run --rm --no-deps --build app composer test
docker compose exec app npm run types:check
docker compose exec app npm run lint:check
docker compose exec app php artisan sellerscope:reconcile ACCOUNT_ID --fail-on-drift
docker compose exec app npm run build
docker compose logs -f app queue scheduler
docker compose down
```

`docker compose down` keeps database and Redis volumes. Do not add `--volumes`
unless local data removal is explicitly intended.

Redis can report that host-level `vm.overcommit_memory` is disabled. The
containers remain usable for normal local development, but enabling that Linux
setting is recommended before load testing or relying on Redis persistence.
