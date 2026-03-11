# Laravel Cloud Operations

## Overview
Redeem-X is deployed on **Laravel Cloud** at `https://redeem-x.laravel.cloud`.
All cloud operations are accessible from the developer's terminal via a `Makefile` and the `cloud` CLI.

## Access Methods

### 1. Makefile (Primary — no dependencies)
All targets use `curl` + `$LARAVEL_CLOUD_TOKEN` directly. No CLI installation required.

```bash
# Prerequisites
export LARAVEL_CLOUD_TOKEN=<token-from-cloud-dashboard>

# Quick reference
make cloud-status        # Environment status (running/hibernating)
make cloud-logs          # Application logs (last 30 min)
make cloud-logs MINUTES=5  # Narrow time window
make cloud-errors        # Error-level logs only
make cloud-access        # HTTP access logs
make cloud-deploys       # Recent deployment history
make cloud-cmd CMD="cache:clear"    # Run artisan command remotely
make cloud-cmd CMD="migrate --force"
make cloud-clear         # Clear all caches (cache, config, route, view)
make cloud-deploy        # Trigger deployment manually
make cloud-env           # List env var keys (values hidden)
make cloud-debug         # Compound: status + recent errors
```

### 2. Cloud CLI (Interactive use)
Installed globally via `composer global require laravel/cloud-cli`.
Auth token stored at `~/.config/cloud/config.json`.
Repo linked via `.cloud/config.json` (auto-resolves app + environment).

```bash
cloud environment:get --json          # Environment details (JSON)
cloud environment:logs                # View logs interactively
cloud command:run                     # Run artisan command
cloud deploy                          # Deploy
cloud deployment:list --json          # List deployments (JSON)

# Set environment variables
cloud environment:variables --action=append --key=KEY --value=VALUE --force --no-interaction
```

### 3. REST API (Direct curl)
Base URL: `https://cloud.laravel.com/api`

```bash
# Environment details
curl -sf -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" -H "Accept: application/json" \
  "https://cloud.laravel.com/api/environments/$ENV_ID"

# Application logs (requires from/to timestamps)
curl -sf -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" -H "Accept: application/json" \
  "https://cloud.laravel.com/api/environments/$ENV_ID/logs?type=application&from=2026-03-04T00:00:00&to=2026-03-04T23:59:59"

# Access logs
# Same endpoint with type=access

# Run remote command (POST, returns command ID)
curl -sf -X POST -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  "https://cloud.laravel.com/api/environments/$ENV_ID/commands" \
  -d '{"command": "php artisan cache:clear"}'

# Poll command result (GET with command ID)
curl -sf -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" -H "Accept: application/json" \
  "https://cloud.laravel.com/api/environments/$ENV_ID/commands/$CMD_ID"

# Trigger deployment
curl -sf -X POST -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  "https://cloud.laravel.com/api/environments/$ENV_ID/deployments" -d '{}'

# List deployments
curl -sf -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" -H "Accept: application/json" \
  "https://cloud.laravel.com/api/environments/$ENV_ID/deployments"
```

## Environment IDs
- **Application**: `app-a06260f2-7717-4c3b-b4e3-f94d83b77cab`
- **Environment (main)**: `env-a06260f2-d81f-4d85-82a3-063db17c7b15`
- **Vanity domain**: `redeem-x.laravel.cloud`

## Environment Configuration
- **PHP**: 8.4
- **Node**: 22
- **Push-to-deploy**: enabled (deploys on `git push origin main`)
- **Hibernation**: enabled (scales to zero when idle, wakes on traffic)
- **LOG_CHANNEL**: `stderr` (writes to stderr → captured by Cloud)
- **LOG_LEVEL**: `debug` (all log levels captured)
- **Build command**: `composer install --no-dev ... && npm ci && npm run build`
- **Deploy command**: `php artisan migrate --force`

## Common AI Agent Workflows

### Debugging Production Issues
```bash
# 1. Quick triage
make cloud-debug

# 2. Check recent deployments for failures
make cloud-deploys

# 3. Narrow log window
make cloud-logs MINUTES=5
make cloud-errors MINUTES=10

# 4. Run diagnostic commands
make cloud-cmd CMD="route:list --json"
make cloud-cmd CMD="config:show database"
```

### After Deploying Code Changes
```bash
# Verify deployment succeeded
make cloud-deploys

# Watch for new errors
make cloud-errors MINUTES=10

# Clear caches if config changed
make cloud-clear
```

### Setting Environment Variables
```bash
# Via CLI (preferred — handles API format correctly)
cloud environment:variables --action=append --key=KEY --value=VALUE --force --no-interaction

# Verify
make cloud-env
```

### Remote Artisan Commands
```bash
# The cloud-cmd target submits the command, polls for completion, and shows output
make cloud-cmd CMD="inspire"
make cloud-cmd CMD="cache:clear"
make cloud-cmd CMD="migrate:status"
make cloud-cmd CMD="tinker --execute=\"App\\Models\\User::count()\""

# Commands must be non-interactive and complete within 15 minutes
```

## Key Files
- `Makefile` — All cloud operation targets
- `.cloud/config.json` — CLI repo-to-app linking (gitignored, per-user)
- `~/.config/cloud/config.json` — CLI auth token (per-user)
- `config/logging.php` — Logging configuration (`stderr` channel for Cloud)

## Logging Architecture
- **Channel**: `stderr` (Monolog StreamHandler → `php://stderr`)
- **Level**: `debug` (captures all: debug, info, notice, warning, error, critical, alert, emergency)
- **Custom tap**: `App\Logging\StderrTap` — increases buffer to 8MB for large payloads
- **Cloud capture**: stderr output is captured by Laravel Cloud and available via logs API
- **Log viewer**: `make cloud-logs` or `make cloud-errors` for filtered view

## Remote Tinker via REST API (Cookbook)

The Cloud REST API is the most reliable way to run tinker commands from AI sessions.
Below are the exact patterns that work — no trial and error needed.

### Prerequisites

```bash
# Token contains a pipe character (e.g. 650|8Nu8a1C...) — MUST use single quotes
export LARAVEL_CLOUD_TOKEN='<token-from-cloud-dashboard>'
```

**Critical**: Always use single quotes around the token value. The `|` in the token
will be interpreted as a shell pipe if double-quoted or unquoted.

### Step 1: Submit Command (POST)

Commands MUST include the `php artisan` prefix — the API runs raw bash, not artisan.

```bash
# Submit and capture command ID
curl -sf -X POST \
  -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"command":"php artisan tinker --execute=\"...\""}'  \
  "https://cloud.laravel.com/api/environments/env-a06260f2-d81f-4d85-82a3-063db17c7b15/commands"
```

Response includes `"id":"comm-XXXXX"` and `"status":"pending"`.

### Step 2: Poll Result (GET)

Poll endpoint uses `/api/commands/{id}` (NOT under `/environments/`).

```bash
# Wait then poll
sleep 6 && curl -sf \
  -H "Authorization: Bearer $LARAVEL_CLOUD_TOKEN" \
  "https://cloud.laravel.com/api/commands/comm-XXXXX"
```

Response includes `"output"`, `"status":"command.success"`, and `"exit_code"`.

### Common Tinker Patterns

**Query database (SELECT):**
```bash
-d '{"command":"php artisan tinker --execute=\"echo json_encode(DB::select(\\\"SELECT id, email, mobile FROM users\\\"));\""}'
```

**Update a record:**
```bash
-d '{"command":"php artisan tinker --execute=\"\\App\\Models\\User::where(\\\"email\\\", \\\"user@example.com\\\")->update([\\\"column\\\" => \\\"value\\\"]);\""}'
```

**Count records:**
```bash
-d '{"command":"php artisan tinker --execute=\"echo \\App\\Models\\User::count();\""}'
```

**Run a plain artisan command (no tinker):**
```bash
-d '{"command":"php artisan migrate:status"}'
```

### Escaping Rules (for -d JSON payload)

The escaping is deeply nested: shell → JSON → bash → PHP. Follow these rules:
- Outer JSON string uses `\"` for double quotes
- PHP namespaces: each `\` becomes `\\\\` (4 backslashes)
- SQL strings inside tinker: use `\\\"` (escaped escaped quotes)
- When in doubt, use `DB::select()` with raw SQL — simpler escaping than Eloquent

### Gotchas
- **No `php artisan` prefix** → `/bin/bash: tinker: command not found`
- **Token without single quotes** → shell interprets `|` as pipe → `Authorization: Bearer 650` (truncated)
- **Polling wrong endpoint** → `{"message":"Unauthenticated"}` if using env-scoped URL instead of `/api/commands/{id}`
- **Tinker output** → tinker `--execute` prints the return value; wrap in `echo json_encode(...)` for structured output
- **Sleep before poll** → 5-8 seconds is usually enough; tinker commands typically finish in 2-3 seconds

## Important Notes
- `$LARAVEL_CLOUD_TOKEN` must be set as an environment variable (never hardcode)
- Push-to-deploy means every `git push origin main` triggers a production deployment
- Environment variable changes via CLI/API also trigger a redeploy
- Commands run remotely are non-interactive and have a 15-minute timeout
- The Makefile hardcodes the environment ID (not secret) but reads the token from env
