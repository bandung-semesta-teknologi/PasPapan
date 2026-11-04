# Docker deployment

This Compose setup builds a small PHP-FPM image and a separate Caddy image. Bun, Composer, and their build dependencies stay in intermediate image stages. PostgreSQL and uploaded application files use named volumes.

## First start

1. Copy `.env.example` to `.env`.
2. Set a strong `DB_PASSWORD` and set `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL` in `.env`.
3. Create the shared network once if it does not already exist:

   ```sh
   docker network create proxy
   ```

4. Build the PHP image:

   ```sh
   docker compose build app
   ```

5. Generate an application key and paste the printed value into `.env` as `APP_KEY`:

   ```sh
   docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
   ```

6. Build and start the stack:

   ```sh
   docker compose up -d --build
   ```

At each application start, the app waits for PostgreSQL, runs `php artisan migrate --force`, runs the idempotent production master-data seeder (`paspapan:seed-real`), creates the public storage link, and runs `php artisan optimize`. The queue worker and scheduler start after the app is healthy.

The production-safe seeder does not create the default bootstrap admin accounts in production. If you enable `BOOTSTRAP_ADMIN_SEEDING_ENABLED=true` for a controlled bootstrap, change the seeded passwords immediately, turn the flag off, and restart the app.

## Reverse proxy

The `web` Caddy service joins the external Docker network named `proxy` and has the network alias `paspapan`. Configure a reverse proxy already attached to that network to use `paspapan:80`. For another Caddy service, the upstream can be:

```caddyfile
your-domain.example {
    reverse_proxy paspapan:80
}
```

The bundled Caddy service listens on HTTP and serves the built public assets; the other proxy can terminate TLS. For local access, open `http://127.0.0.1:8080`; the bundled service binds that port on loopback only. Set `APP_HTTP_PORT` in `.env` to change it.

## Operations

```sh
docker compose logs -f app web queue scheduler db
docker compose exec app php artisan migrate:status
docker compose down
```

`docker compose down` preserves the PostgreSQL and storage volumes. To override the default startup migration or seeder behavior for a release process, set `RUN_MIGRATIONS=false` or `RUN_SEEDERS=false` in `.env`. Do not run multiple app replicas with startup migrations enabled; execute migrations once as a release step instead.

## GitHub Actions deployment

`.github/workflows/deploy-compose.yml` deploys after a successful CI push to `main-vps`. It syncs the source to `/var/www/paspapan-compose`, builds the `app` and `web` images on the VPS, then runs `docker compose up` and waits for the services to become healthy. It does not use GHCR.

Prepare the VPS before enabling the workflow:

- Install Docker Engine with the Docker Compose plugin v2.20 or newer, plus `rsync`.
- Give the SSH deploy user permission to run `docker` and write to `/var/www/paspapan-compose`.
- Create the shared network with `docker network create proxy`.
- Create `/var/www/paspapan-compose/.env` with production settings, including `APP_KEY`, `APP_URL`, `DB_PASSWORD`, `DB_DATABASE`, and `DB_USERNAME`. The workflow preserves this file and never syncs it from GitHub.

Create a GitHub Environment named `production`, restrict deployments to `main-vps`, and add these Environment secrets:

| Secret | Value |
| --- | --- |
| `HOST` | VPS hostname or IP, without a scheme or port |
| `USER` | SSH deploy username |
| `SSH_KEY` | Private SSH key whose public key is in the deploy user's `authorized_keys` |
| `SSH_KNOWN_HOSTS` | Preverified `known_hosts` line(s) for the VPS; verify the fingerprint out of band before saving |

The workflow uses SSH port 22. It deploys automatically after CI passes on a push to `main-vps`; manual dispatch is also limited to `main-vps`. The job uses the `production` Environment, so its branch policy and any required reviewer rules apply before deployment starts.
