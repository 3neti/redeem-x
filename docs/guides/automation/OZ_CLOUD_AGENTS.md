# Oz Cloud Agents

Run cloud agents against this codebase for parallel development tasks, background automation, and long-running work.

## Prerequisites

- [Warp](https://warp.dev) desktop app (includes the `oz` CLI)
- Authenticated: `oz login`
- Docker Hub access to `lesterhurtado/warp-env-redeem-x`

## Environment

| Key | Value |
|-----|-------|
| **Environment ID** | `fsGb0ZRFcjPtie1pxawzRy` |
| **Docker image** | `lesterhurtado/warp-env-redeem-x:latest` |
| **Base** | PHP 8.4 + Composer + Node.js 22 + SQLite |
| **Repo** | `3neti/redeem-x` |

The environment automatically runs on boot:
1. `composer install`
2. `cp .env.example .env` + `php artisan key:generate`
3. `php artisan migrate --force`
4. `npm install` + `npm run build`

## Running Cloud Agents

### Basic usage

```bash
oz agent run-cloud \
  --environment fsGb0ZRFcjPtie1pxawzRy \
  --prompt "Your task description"
```

### Examples

```bash
# Run the test suite
oz agent run-cloud -e fsGb0ZRFcjPtie1pxawzRy \
  --prompt "Run php artisan test and report results"

# Code task with PR
oz agent run-cloud -e fsGb0ZRFcjPtie1pxawzRy \
  --prompt "Add validation for negative amounts in GenerateVouchers action. Create a PR."

# Refactoring
oz agent run-cloud -e fsGb0ZRFcjPtie1pxawzRy \
  --prompt "Find all debug log statements and remove them. Create a PR."
```

### Monitor runs

```bash
# List recent runs
oz run list

# Get details of a specific run
oz run get <RUN_ID>
```

You can also view runs at [oz.warp.dev](https://oz.warp.dev) or click the session link printed when a run starts.

## Rebuilding the Docker Image

If you need to update the Docker image (e.g., new PHP extensions, different Node version):

1. Edit the `Dockerfile` in the project root
2. Build and push for **amd64** (required by Oz cloud infrastructure):

```bash
docker buildx build --platform linux/amd64 --provenance=false --sbom=false \
  -t lesterhurtado/warp-env-redeem-x:latest --push .
```

> **Important:** Always include `--provenance=false --sbom=false` — Oz cannot handle the attestation metadata that `buildx` adds by default.

3. The environment will automatically use the updated image on the next run (no environment update needed).

## Updating the Environment

```bash
# Add a setup command
oz environment update fsGb0ZRFcjPtie1pxawzRy \
  --setup-command "cd redeem-x && php artisan db:seed"

# Remove a setup command (must match exactly)
oz environment update fsGb0ZRFcjPtie1pxawzRy \
  --remove-setup-command "cd redeem-x && php artisan db:seed"

# View current config
oz environment get fsGb0ZRFcjPtie1pxawzRy
```

## Adding Secrets

For tasks requiring API keys (not needed for basic dev work):

```bash
oz secret create SECRET_NAME --value-file path/to/secret.txt
```

## Troubleshooting

**"The request contains invalid or missing parameters"**
- Usually means the Docker image is incompatible. Rebuild with `--platform linux/amd64 --provenance=false --sbom=false`.

**Setup commands failing**
- Check that the Dockerfile includes all required system packages.
- Run `oz environment get fsGb0ZRFcjPtie1pxawzRy` to verify setup commands.

**Agent can't push/create PRs**
- Ensure the Warp GitHub App has write access to `3neti/redeem-x` at [GitHub Settings > Applications](https://github.com/settings/installations).
