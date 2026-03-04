# =============================================================================
# Redeem-X Makefile — Laravel Cloud Operations
# =============================================================================
#
# Prerequisites:
#   export LARAVEL_CLOUD_TOKEN=<your-api-token>
#
# Usage:
#   make cloud-status       # Environment status
#   make cloud-logs         # Recent error logs
#   make cloud-cmd CMD="cache:clear"
#
# All targets use the Cloud REST API directly (no CLI dependency).
# =============================================================================

SHELL := /bin/bash
.DEFAULT_GOAL := cloud-help

# — Configuration —————————————————————————————————————————————————————————————
CLOUD_API     := https://cloud.laravel.com/api
CLOUD_APP_ID  := app-a06260f2-7717-4c3b-b4e3-f94d83b77cab
CLOUD_ENV_ID  := env-a06260f2-d81f-4d85-82a3-063db17c7b15
CLOUD_DOMAIN  := redeem-x.laravel.cloud

# — Helpers ———————————————————————————————————————————————————————————————————
define check_token
	@if [ -z "$$LARAVEL_CLOUD_TOKEN" ]; then \
		echo "❌ LARAVEL_CLOUD_TOKEN is not set. Export it first:"; \
		echo "   export LARAVEL_CLOUD_TOKEN=<your-token>"; \
		exit 1; \
	fi
endef

CURL_GET = curl -sf -H "Authorization: Bearer $$LARAVEL_CLOUD_TOKEN" -H "Accept: application/json"
CURL_POST = curl -sf -X POST -H "Authorization: Bearer $$LARAVEL_CLOUD_TOKEN" -H "Accept: application/json" -H "Content-Type: application/json"

# — Cloud: Status —————————————————————————————————————————————————————————————

.PHONY: cloud-status
cloud-status: ## Show environment status, PHP version, hibernation, domain
	$(check_token)
	@$(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)" \
		| python3 -c "import sys,json; d=json.load(sys.stdin)['data']['attributes']; \
		print(f\"Environment:  {d['name']}\"); \
		print(f\"Status:       {d['status']}\"); \
		print(f\"Domain:       {d['vanity_domain']}\"); \
		print(f\"PHP:          {d['php_major_version']}\"); \
		print(f\"Node:         {d['node_version']}\"); \
		print(f\"Hibernation:  {d['uses_hibernation']}\"); \
		print(f\"Push-deploy:  {d['uses_push_to_deploy']}\")"

# — Cloud: Logs ———————————————————————————————————————————————————————————————

MINUTES ?= 30

.PHONY: cloud-logs
cloud-logs: ## Recent application error logs (MINUTES=30)
	$(check_token)
	@FROM=$$(date -u -v-$(MINUTES)M +%Y-%m-%dT%H:%M:%S 2>/dev/null || date -u -d "$(MINUTES) minutes ago" +%Y-%m-%dT%H:%M:%S); \
	TO=$$(date -u +%Y-%m-%dT%H:%M:%S); \
	$(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/logs?type=application&from=$$FROM&to=$$TO" \
		| python3 -c "import sys,json; \
		data=json.load(sys.stdin).get('data',[]); \
		[print(f\"{e.get('logged_at','')} [{e.get('level','?')}] {e.get('message','')}\") for e in data] if data else print('No logs in the last $(MINUTES) minutes.')"

.PHONY: cloud-access
cloud-access: ## Recent access logs (MINUTES=30)
	$(check_token)
	@FROM=$$(date -u -v-$(MINUTES)M +%Y-%m-%dT%H:%M:%S 2>/dev/null || date -u -d "$(MINUTES) minutes ago" +%Y-%m-%dT%H:%M:%S); \
	TO=$$(date -u +%Y-%m-%dT%H:%M:%S); \
	$(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/logs?type=access&from=$$FROM&to=$$TO" \
		| python3 -c "import sys,json; \
		data=json.load(sys.stdin).get('data',[]); \
		[print(f\"{e.get('logged_at','')} {e.get('data',{}).get('method','?')} {e.get('data',{}).get('path','?')} → {e.get('data',{}).get('status','?')} ({e.get('data',{}).get('duration_ms',0)}ms)\") for e in data] if data else print('No access logs in the last $(MINUTES) minutes.')"

.PHONY: cloud-errors
cloud-errors: ## Recent error-level logs only (MINUTES=30)
	$(check_token)
	@FROM=$$(date -u -v-$(MINUTES)M +%Y-%m-%dT%H:%M:%S 2>/dev/null || date -u -d "$(MINUTES) minutes ago" +%Y-%m-%dT%H:%M:%S); \
	TO=$$(date -u +%Y-%m-%dT%H:%M:%S); \
	$(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/logs?type=application&level=error&from=$$FROM&to=$$TO" \
		| python3 -c "import sys,json; \
		data=json.load(sys.stdin).get('data',[]); \
		[print(f\"{e.get('logged_at','')} {e.get('message','')}\") for e in data] if data else print('✅ No errors in the last $(MINUTES) minutes.')"

# — Cloud: Deployments ————————————————————————————————————————————————————————

.PHONY: cloud-deploys
cloud-deploys: ## List recent deployments
	$(check_token)
	@$(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/deployments" \
		| python3 -c "import sys,json; \
		data=json.load(sys.stdin).get('data',[]); \
		[print(f\"{d['attributes'].get('status','?'):12} {d['attributes'].get('commit_hash','?')[:8]} {d['attributes'].get('commit_message','')[:60]:60} {d['attributes'].get('started_at','')}\") for d in data[:10]]"

.PHONY: cloud-deploy
cloud-deploy: ## Trigger a new deployment
	$(check_token)
	@echo "Triggering deployment for $(CLOUD_DOMAIN)..."
	@$(CURL_POST) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/deployments" -d '{}' \
		| python3 -c "import sys,json; d=json.load(sys.stdin)['data']; \
		print(f\"Deployment {d['id']} — status: {d['attributes']['status']}\")"

# — Cloud: Remote Commands ————————————————————————————————————————————————————

CMD ?=

.PHONY: cloud-cmd
cloud-cmd: ## Run artisan command remotely: make cloud-cmd CMD="cache:clear"
	$(check_token)
	@if [ -z "$(CMD)" ]; then \
		echo "Usage: make cloud-cmd CMD=\"cache:clear\""; \
		exit 1; \
	fi
	@echo "Running: php artisan $(CMD)"
	@RESULT=$$($(CURL_POST) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/commands" \
		-d "{\"command\": \"php artisan $(CMD)\"}" 2>&1); \
	CMD_ID=$$(echo "$$RESULT" | python3 -c "import sys,json; print(json.load(sys.stdin)['data']['id'])" 2>/dev/null); \
	if [ -z "$$CMD_ID" ]; then \
		echo "❌ Failed to submit command"; \
		echo "$$RESULT"; \
		exit 1; \
	fi; \
	echo "Command ID: $$CMD_ID — polling..."; \
	for i in $$(seq 1 60); do \
		sleep 2; \
		STATUS=$$($(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)/commands/$$CMD_ID" \
			| python3 -c "import sys,json; d=json.load(sys.stdin)['data']['attributes']; print(d['status']+'|||'+str(d.get('exit_code',''))+'|||'+str(d.get('output','')))" 2>/dev/null); \
		S=$$(echo "$$STATUS" | cut -d'|' -f1); \
		if [ "$$S" = "command.success" ] || [ "$$S" = "command.failed" ] || [ "$$S" = "command.timed_out" ]; then \
			EXIT=$$(echo "$$STATUS" | cut -d'|' -f4); \
			OUTPUT=$$(echo "$$STATUS" | cut -d'|' -f7-); \
			echo "Status: $$S (exit: $$EXIT)"; \
			if [ -n "$$OUTPUT" ] && [ "$$OUTPUT" != "None" ]; then \
				echo "---"; \
				echo "$$OUTPUT"; \
			fi; \
			break; \
		fi; \
	done

# — Cloud: Environment Variables ——————————————————————————————————————————————

.PHONY: cloud-env
cloud-env: ## List environment variable keys (values hidden for safety)
	$(check_token)
	@$(CURL_GET) "$(CLOUD_API)/environments/$(CLOUD_ENV_ID)" \
		| python3 -c "import sys,json; \
		vars=json.load(sys.stdin)['data']['attributes']['environment_variables']; \
		[print(f\"  {v['key']}\") for v in vars]"

# — Cloud: Compound ———————————————————————————————————————————————————————————

.PHONY: cloud-debug
cloud-debug: ## Quick triage: status + recent errors
	@echo "=== Environment Status ==="
	@$(MAKE) --no-print-directory cloud-status
	@echo ""
	@echo "=== Recent Errors (last $(MINUTES) min) ==="
	@$(MAKE) --no-print-directory cloud-errors

.PHONY: cloud-clear
cloud-clear: ## Clear all caches remotely
	@echo "Clearing caches on $(CLOUD_DOMAIN)..."
	@$(MAKE) --no-print-directory cloud-cmd CMD="cache:clear"
	@$(MAKE) --no-print-directory cloud-cmd CMD="config:clear"
	@$(MAKE) --no-print-directory cloud-cmd CMD="route:clear"
	@$(MAKE) --no-print-directory cloud-cmd CMD="view:clear"
	@echo "✅ All caches cleared."

# — Cloud: Help ———————————————————————————————————————————————————————————————

.PHONY: cloud-help
cloud-help: ## Show all cloud targets
	@echo "Redeem-X Cloud Operations"
	@echo "========================"
	@echo ""
	@echo "Prerequisites: export LARAVEL_CLOUD_TOKEN=<token>"
	@echo "Environment:   $(CLOUD_DOMAIN) ($(CLOUD_ENV_ID))"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
