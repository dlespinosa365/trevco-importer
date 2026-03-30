# Trevco Importer

A Laravel application for **orchestrating integration flows**: import and sync data between your systems and external vendors (for example NetSuite, marketplaces, and internal APIs). Flows are defined **on disk** under `integrations/`, executed **asynchronously** on queues, and monitored through a **Filament** admin panel.

**Repository:** [github.com/dlespinosa365/trevco-importer](https://github.com/dlespinosa365/trevco-importer)

## What it does

- **Disk-based integrations** — Each integration is a folder with `config.php`, optional group configs, and **flows** made of PHP **steps** (`Step` interface) that pass context forward.
- **Connectors** — Credentials and typed vendor clients live in the database (encrypted), managed in Filament; steps resolve them via `ConnectorsHelper`.
- **Execution model** — A run creates `FlowExecution` / `StepExecution` rows. `ExecuteIntegrationFlowJob` starts a chain; `ExecuteIntegrationStepJob` runs one step and dispatches the next (or completes / fails).
- **Fan-out** — A step can spawn one child execution per item so downstream work stays isolated and parallelizable.
- **Scheduling & triggers** — Flow schedules, manual runs, CLI (`flows:run`), and webhooks-style entry points (first step override).
- **Failure handling** — Configurable mail / Slack / Teams-style notifications; stale `RUNNING` runs can be reconciled via an Artisan command.

## Tech stack

| Area | Choice |
|------|--------|
| Framework | Laravel 13, PHP 8.3+ |
| Admin UI | Filament v5 |
| Queues (production) | Redis + **Laravel Horizon** |
| Permissions | Spatie Laravel Permission |
| Tests | Pest 4 |

## Quick start (local)

```bash
git clone https://github.com/dlespinosa365/trevco-importer.git
cd trevco-importer

composer install
cp .env.example .env
php artisan key:generate

php artisan migrate --seed   # admin user + roles (see seeders / Filament docs)

npm install && npm run build   # or npm run dev during frontend work

php artisan serve
```

Open the app URL (for [Laravel Herd](https://herd.laravel.com), typically `https://{project-folder}.test`, or `http://localhost:8000` with `php artisan serve`). Log in with the seeded Filament admin credentials from `.env` / `FilamentAdminUserSeeder` (override in production).

For **queue-driven flows** without Horizon on Windows, you can use `QUEUE_CONNECTION=database` or `sync` for light testing; **Horizon** targets Linux/WSL/Docker (`ext-pcntl`, `ext-posix`).

## Useful Artisan commands

| Command | Purpose |
|---------|---------|
| `php artisan flows:validate` | Validate every discovered `flow_ref` and step wiring |
| `php artisan flows:run {flow_ref}` | Run a flow (async by default; `--sync` for debugging) |
| `php artisan flows:schedule-runner` | Dispatch due schedules (normally run by the scheduler) |
| `php artisan flows:reconcile-stale-running` | Mark orphaned `RUNNING` executions failed |

See **`php artisan list`** and **`docs/DEVELOPER_GUIDE.md`** for the full command reference.

## Documentation

| Document | Contents |
|----------|----------|
| **[docs/DEVELOPER_GUIDE.md](docs/DEVELOPER_GUIDE.md)** | Directory layout, `flow_ref`, steps, connectors, fan-out, queues, Horizon, idempotency, heartbeats, business-rule errors |
| **[docs/ARCHITECTURE_REVIEW.md](docs/ARCHITECTURE_REVIEW.md)** | Queue semantics, `retry_after`, staleness, operational tuning |
| **[AGENTS.md](AGENTS.md)** | AI / editor conventions for this repo (Laravel Boost, testing, Pint) |

## Testing

```bash
php artisan test --compact
```

Run a subset: `php artisan test --compact tests/Feature/IntegrationDiskFlowTest.php`

## Production checklist

- `APP_ENV=production`, secure `APP_KEY`, real `APP_URL`
- `QUEUE_CONNECTION=redis`, Redis available, **`php artisan horizon`** under Supervisor/systemd
- Cron or platform scheduler running **`php artisan schedule:run`** (flows schedule + reconcile + Horizon snapshot)
- Tune `FLOW_LONGEST_STEP_SECONDS`, Horizon supervisors, and notification channels as needed

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT). Laravel and bundled packages retain their respective licenses.
