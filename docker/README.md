# Docker deployment

This Compose setup builds a small PHP-FPM image and a separate Caddy image. Bun, Composer, and their build dependencies stay in intermediate image stages. PostgreSQL and uploaded application files use named volumes.

## First start

1. Copy `.env.example` to `.env`.
2. Set `APP_DOMAIN` to the public hostname (without scheme), `APP_URL=https://<APP_DOMAIN>`, a stable `APP_KEY`, and a strong `DB_PASSWORD` in `.env`.
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

The `web` Caddy service joins the external Docker network named `proxy` and has the network alias `paspapan`. Its Caddyfile serves only `http://{$APP_DOMAIN}`, serves public assets, and forwards PHP requests to PHP-FPM at `app:9000`. Configure the existing public reverse proxy attached to `proxy` with the same hostname and use `paspapan:80` as its upstream. For another Caddy service, use:

```caddyfile
your-domain.example {
    reverse_proxy paspapan:80
}
```

The existing public proxy can terminate TLS. Set `APP_DOMAIN` in `.env` to the same bare hostname (`your-domain.example` in the example); the Compose workflow derives Laravel's `APP_URL` as `https://<APP_DOMAIN>`. For local access, open `http://127.0.0.1:8080`; the bundled service binds that port on loopback only. Set `APP_HTTP_PORT` in `.env` to change it.

## Operations

```sh
docker compose logs -f app web queue scheduler db
docker compose exec app php artisan migrate:status
docker compose down
```

`docker compose down` preserves the PostgreSQL and storage volumes. To override the default startup migration or seeder behavior for a release process, set `RUN_MIGRATIONS=false` or `RUN_SEEDERS=false` in `.env`. Do not run multiple app replicas with startup migrations enabled; execute migrations once as a release step instead.

## GitHub Actions deployment

`.github/workflows/deploy-compose.yml` deploys after a successful CI push to `main-vps`, or by manual dispatch from the selected branch. It writes `.env` from the `production` GitHub Environment, syncs source to `DEPLOY_PATH`, builds the `app` and `web` images on the VPS, then runs `docker compose up` and waits for the services to become healthy. It does not use GHCR.

Prepare the VPS before enabling the workflow:

- Install Docker Engine with the Docker Compose plugin v2.20 or newer, plus `rsync`.
- Give the SSH deploy user permission to run Docker and write to `DEPLOY_PATH`.
- Create the shared network with `docker network create proxy`.

Create a GitHub Environment named `production`. Add these Environment variables:

| Variable | Value |
| --- | --- |
| `USER` | SSH deploy username, usually `deploy-apps` |
| `DEPLOY_PATH` | Absolute remote application directory |
| `APP_DOMAIN` | Public hostname only, without scheme, port, or path |

Add these Environment secrets:

| Secret | Value |
| --- | --- |
| `HOST` | VPS hostname or IP, without a scheme or port |
| `SSH_KEY` | Private SSH key whose public key is in the deploy user's `authorized_keys` |
| `SSH_KNOWN_HOSTS` | Preverified `known_hosts` line(s) for the VPS; verify the fingerprint out of band before saving |
| `DEPLOY_ENV` | Multiline production `.env` contents, including `APP_KEY`, `DB_PASSWORD`, and database settings |

The workflow writes `APP_DOMAIN` and `APP_URL=https://<APP_DOMAIN>` from the Environment variable, overriding those keys if present in `DEPLOY_ENV`. Keep `APP_KEY` stable across deploys. Put optional SMTP configuration, including `MAIL_PASSWORD`, in `DEPLOY_ENV`. The workflow uses SSH port 22 and the `production` Environment's branch policy and reviewer rules.
