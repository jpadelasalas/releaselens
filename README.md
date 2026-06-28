# ReleaseLens

ReleaseLens is a React and Laravel application for inspecting pull-request review flow. It supports a deterministic public demo and private GitHub App workspaces.

## Local Development

Requirements: PHP 8.3, Composer, Node.js 20, npm, and PostgreSQL or SQLite.

```powershell
cd backend
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Run the background processes in separate terminals:

```powershell
cd backend
php artisan queue:work --tries=3 --timeout=840
```

```powershell
cd backend
php artisan schedule:work
```

Run the frontend:

```powershell
cd frontend
npm ci
npm run dev
```

`Sync Now` creates a database queue job. Without `queue:work`, synchronization remains queued and no pull requests are imported.

## Container Topology

The production-style image compiles the React application and serves it from the same Apache/Laravel origin. `compose.yaml` runs the image in four roles:

- `web`: Apache, the React SPA, and the Laravel API.
- `worker`: persistent database queue processing for GitHub synchronization.
- `scheduler`: scheduled polling every six hours.
- `migrate`: one-time database migrations before application processes start.

PostgreSQL runs as the `database` service for local container use.

Create the container environment and start the stack:

```powershell
Copy-Item docker/.env.example .env
cd backend
php artisan key:generate --show
cd ..
# Put the generated APP_KEY in .env, then:
docker compose up --build
```

Open `http://localhost:8080`. Readiness is available at `http://localhost:8080/api/v1/health`; lightweight liveness remains at `/up`.

## Deployment

The recommended V1 topology is one origin for the SPA and API. Deploy the root `Dockerfile` as the web service, then run the same image as separate worker and scheduler processes.

Web command:

```text
apache2-foreground
```

Worker command:

```text
php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=840 --max-time=3600 --no-interaction
```

Scheduler command:

```text
php artisan schedule:work --no-interaction
```

Run this as a release or pre-deploy command:

```text
php artisan migrate --force --no-interaction
```

Required hosted configuration includes `APP_KEY`, `APP_URL`, `CLIENT_URL`, PostgreSQL credentials or `DB_URL`, and the GitHub App variables. Use `APP_ENV=production`, `APP_DEBUG=false`, `LOG_CHANNEL=stderr`, `QUEUE_CONNECTION=database`, `SESSION_SECURE_COOKIE=true`, and database TLS for hosted environments.

The GitHub App callback URL is:

```text
https://YOUR_DOMAIN/api/v1/github/callback
```

A split Vercel frontend and remote Laravel API is possible, but it requires deliberate CORS, CSRF, cookie-domain, HTTPS, and callback configuration. The single-origin image avoids that complexity.

## Verification

```powershell
cd backend
php artisan test
vendor\bin\pint --test
```

```powershell
cd frontend
npm run lint
npm run type-check
npm test
npm run build
npm run test:e2e
```
