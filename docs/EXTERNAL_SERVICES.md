# Redeem-X — External Services & Subscriptions

This document lists every internet service required to operate Redeem-X in production. It is intended for IT procurement: each entry states what the service does, whether it is **Required** or **Optional**, what triggers the need for it, and the environment variables that must be configured.

---

## Quick Reference

**Minimum viable deployment** (core voucher generation + disbursement):
- Laravel Cloud (hosting)
- GitHub (source code + CI/CD)
- NetBank (payment gateway)
- One email provider (Resend, Postmark, or SMTP)
- Domain registrar

**Full-featured deployment** adds:
- WorkOS (SSO, social login, MFA)
- EngageSpark (SMS)
- HyperVerge (KYC)
- AWS S3 (file storage)
- Pusher (real-time updates)
- OpenCage + Mapbox (location features)
- Cloudflare (social media previews)
- Telegram Bot API (messaging bot)
- Pipedream (SMS shortcode gateway)

---

## 1. Cloud Infrastructure

### Laravel Cloud — Application Hosting

| | |
|---|---|
| **Status** | **Required** |
| **Purpose** | Hosts the application. Provides PHP runtime, Node.js build pipeline, managed database, cache, queue workers, edge CDN, push-to-deploy, and hibernation. |
| **URL** | https://cloud.laravel.com |
| **Production domain** | `redeem-x.laravel.cloud` (custom domain configurable) |

**What you get:** A fully managed platform — no need to provision servers, databases, or load balancers separately. Laravel Cloud bundles compute (app cluster), database (SQLite, MySQL, or PostgreSQL), cache (Redis), queue processing, and edge/CDN into a single subscription.

**Environment variables:**
```
LARAVEL_CLOUD_TOKEN=          # API token for CLI/REST operations (Makefile targets)
```

**Alternative:** Any PHP 8.2+ hosting with Node 22, a relational database, Redis, and a queue driver. Examples: AWS EC2 + RDS, DigitalOcean App Platform, Railway.

---

### AWS S3 — Object Storage

| | |
|---|---|
| **Status** | **Optional** (Required for multi-server deployments) |
| **Purpose** | Stores uploaded files: envelope documents, KYC ID images, selfie captures, signatures, and media library assets. |
| **URL** | https://aws.amazon.com/s3 |

Single-server deployments can use local disk storage. S3 becomes required when running multiple application servers (files must be shared across instances) or when durable, off-server storage is needed.

**Environment variables:**
```
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
```

**Alternative:** Any S3-compatible object storage (MinIO, DigitalOcean Spaces, Cloudflare R2).

---

### GitHub — Source Code & CI/CD

| | |
|---|---|
| **Status** | **Required** |
| **Purpose** | Hosts the source code repository. Laravel Cloud integrates with GitHub for push-to-deploy: every push to `main` triggers an automatic production deployment. |
| **URL** | https://github.com |

**No application environment variables.** GitHub access is configured at the Laravel Cloud level during initial setup.

---

## 2. Authentication & Identity

### WorkOS AuthKit — Enhanced Authentication

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Adds SSO (Google, Microsoft), social login, and multi-factor authentication on top of Laravel's built-in authentication. |
| **URL** | https://workos.com |
| **When required** | When enterprise SSO, social login, or managed MFA is desired. |

The application supports Laravel's built-in authentication as the default. WorkOS can be enabled for organizations that need SSO integration, social login providers, or centrally managed MFA.

**Environment variables:**
```
WORKOS_CLIENT_ID=
WORKOS_API_KEY=
WORKOS_REDIRECT_URL=https://your-domain.com/authenticate
```

---

### HyperVerge — KYC Identity Verification

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Performs Know-Your-Customer verification during voucher redemption. The redeemer completes a selfie + government ID check on their phone. Results are stored against the contact record. |
| **URL** | https://hyperverge.co |
| **When required** | When the `kyc` input field is enabled on a voucher's instructions. Without HyperVerge credentials, KYC-enabled vouchers cannot be redeemed. |

Once a contact is verified, they can redeem multiple KYC-required vouchers without re-verification.

**Environment variables:**
```
HYPERVERGE_APP_ID=
HYPERVERGE_APP_KEY=
HYPERVERGE_BASE_URL=https://ind.idv.hyperverge.co/v1
HYPERVERGE_URL_WORKFLOW=onboarding
HYPERVERGE_WEBHOOK_SECRET=       # For receiving verification results
```

---

## 3. Payment Gateway

All three NetBank services below are provided by the same vendor (**NetBank Philippines**). They use separate API credentials.

### NetBank Omnipay — Disbursement & QR

| | |
|---|---|
| **Status** | **Required** (for disbursement) |
| **Purpose** | Disburses funds to redeemers via INSTAPAY (real-time, ≤₱50k) or PESONET (next business day, ≤₱1M). Also generates QR Ph codes for receiving payments and checks transaction status. |
| **URL** | https://netbank.ph |

This is the core payment rail. Without it, vouchers can be generated and redeemed but no money moves.

**Environment variables:**
```
USE_OMNIPAY=true
PAYMENT_GATEWAY=netbank
NETBANK_CLIENT_ID=
NETBANK_CLIENT_SECRET=
NETBANK_TOKEN_ENDPOINT=https://api-sandbox.netbank.ph/v1/oauth/token
NETBANK_DISBURSEMENT_ENDPOINT=https://api-sandbox.netbank.ph/v1/transfer
NETBANK_QR_ENDPOINT=https://api-sandbox.netbank.ph/v1/qr/generate
NETBANK_STATUS_ENDPOINT=https://api-sandbox.netbank.ph/v1/transactions
NETBANK_BALANCE_ENDPOINT=https://api-sandbox.netbank.ph/v1/accounts
NETBANK_SOURCE_ACCOUNT_NUMBER=
NETBANK_SENDER_CUSTOMER_ID=
NETBANK_CLIENT_ALIAS=
NETBANK_TEST_MODE=true           # Set to false for production
```

---

### NetBank Direct Checkout — Top-Up / Pay-In

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Allows users to add funds to their wallet by redirecting to GCash, Maya, BDO, BPI, or other bank apps. Used for the `/topup` flow. |
| **When required** | When self-service wallet top-up is enabled. |

**Environment variables:**
```
NETBANK_DIRECT_CHECKOUT_ACCESS_KEY=
NETBANK_DIRECT_CHECKOUT_SECRET_KEY=
NETBANK_DIRECT_CHECKOUT_ENDPOINT=https://api-sandbox.netbank.ph/v1/collect/checkout
NETBANK_DIRECT_CHECKOUT_USE_FAKE=true   # Set to false for production
```

---

### NetBank Account-as-a-Service — Customer Accounts

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Creates customer bank accounts programmatically via API. For advanced account management use cases. |

**Environment variables:**
```
NETBANK_CUSTOMER_ENDPOINT=https://api-sandbox.netbank.ph/v1/customer
NETBANK_ACCOUNT_ENDPOINT=https://api-sandbox.netbank.ph/v1/accounts
NETBANK_ACCOUNT_TYPES_ENDPOINT=https://api-sandbox.netbank.ph/v1/account-types
```

---

## 4. Messaging & Notifications

### EngageSpark — SMS

| | |
|---|---|
| **Status** | **Optional** (Required if SMS notifications are enabled) |
| **Purpose** | Sends SMS messages: voucher generation confirmations, redemption receipts, OTP codes, balance notifications. Also powers the SMS command interface (GENERATE, REDEEM, BALANCE). |
| **URL** | https://www.engagespark.com |
| **When required** | When `VOUCHERS_GENERATED_CHANNELS` includes `engage_spark`, or when OTP SMS provider is set to `engagespark`. |

**Environment variables:**
```
ENGAGESPARK_API_KEY=
ENGAGESPARK_ORGANIZATION_ID=
ENGAGESPARK_SENDER_ID=           # Default: serbis.io
```

---

### Email Provider — Transactional Email

| | |
|---|---|
| **Status** | **Required** (one provider) |
| **Purpose** | Sends transactional emails: voucher notifications, disbursement failure alerts, and system notifications. |

Choose **one** of the following:

**Resend** (recommended) — https://resend.com
```
MAIL_MAILER=resend
RESEND_KEY=
```

**Postmark** — https://postmarkapp.com
```
MAIL_MAILER=postmark
POSTMARK_API_KEY=
```

**Amazon SES** — https://aws.amazon.com/ses
```
MAIL_MAILER=ses
# Uses the same AWS credentials from Section 1
```

**Any SMTP provider** (Mailgun, SendGrid, etc.)
```
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_SCHEME=tls
```

All providers share:
```
MAIL_FROM_ADDRESS=hello@your-domain.com
MAIL_FROM_NAME="Your App Name"
```

---

### Pusher — Real-Time Broadcasting

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Delivers real-time WebSocket events to the browser: payment status updates, notification badges, live data refreshes. |
| **URL** | https://pusher.com |

Without Pusher, the application still works — users just need to manually refresh to see updates.

**Environment variables:**
```
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1
```

**Alternative:** Laravel Reverb (self-hosted, free, open-source). Requires a persistent WebSocket process on your server.

---

### Telegram Bot API — Messaging Bot

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Operates a Telegram bot that lets users generate vouchers, check balances, redeem vouchers, and disburse funds through Telegram chat commands. |
| **URL** | https://core.telegram.org/bots/api |
| **When required** | When Telegram-based voucher operations are desired. |

The bot is created via Telegram's BotFather (free). No paid subscription.

**Environment variables:**
```
TELEGRAM_BOT_ENABLED=true
TELEGRAM_BOT_TOKEN=              # From BotFather
TELEGRAM_WEBHOOK_SECRET=
TELEGRAM_BOT_USERNAME=           # e.g., xchange_paycode_bot
TELEGRAM_ADMIN_CHAT_IDS=         # Comma-separated admin chat IDs
```

---

## 5. Mapping & Geocoding

These services are only needed when the **location** input field is enabled on voucher instructions (e.g., requiring proof of location during redemption).

### OpenCage — Reverse Geocoding

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Converts GPS coordinates (latitude/longitude) into a human-readable address. Used during location capture in the redemption flow. |
| **URL** | https://opencagedata.com |
| **Free tier** | 2,500 requests/day |

**Environment variables:**
```
VITE_OPENCAGE_KEY=
```

---

### Mapbox — Static Map Images

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Generates static map image snapshots showing the redeemer's location. Stored alongside the redemption record for auditing. |
| **URL** | https://www.mapbox.com |
| **Free tier** | 50,000 requests/month |

**Environment variables:**
```
VITE_MAPBOX_TOKEN=
```

**Alternative:** Google Maps Static API (`GOOGLE_MAPS_API_KEY`). No free tier without an API key.

---

## 6. Domain & Edge Services

### Domain Registrar

| | |
|---|---|
| **Status** | **Required** |
| **Purpose** | Provides the custom domain name and DNS management. Laravel Cloud provisions an SSL certificate automatically for `.test` and custom domains. |

No environment variables — configured at the DNS and Laravel Cloud level.

---

### Cloudflare — Browser Rendering

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Generates Open Graph (OG) preview images for social media link sharing. When someone shares a voucher link on Facebook, WhatsApp, or Slack, the preview card with the voucher code and amount is rendered by Cloudflare's headless browser. |
| **URL** | https://www.cloudflare.com |
| **When required** | When social media link previews with dynamic images are desired. |

Without this, shared links will show a generic preview (text only, no dynamic image).

**Environment variables:**
```
CLOUDFLARE_API_TOKEN=
CLOUDFLARE_ACCOUNT_ID=
LARAVEL_SCREENSHOT_DRIVER=cloudflare
```

---

## 7. Integration Platforms

### Pipedream — SMS Shortcode Gateway

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | Acts as an authentication proxy between the Omni Channel SMS shortcode (22560537) and the Laravel application. Handles the AUTHENTICATE command and token storage; routes all other SMS commands (GENERATE, REDEEM, BALANCE) to Laravel. |
| **URL** | https://pipedream.com |
| **Free tier** | Sufficient for typical usage |
| **When required** | When SMS-based voucher operations via a shortcode are desired. |

Pipedream is configured through its web dashboard, not through application environment variables. The application exposes `/sms` and `/sms/public` API endpoints that Pipedream calls.

---

## 8. OTP Delivery

### txtcmdr — OTP API

| | |
|---|---|
| **Status** | **Optional** |
| **Purpose** | External API for sending one-time password (OTP) codes via SMS during the redemption flow. Alternative to using EngageSpark for OTP delivery. |
| **When required** | When `OTP_SMS_PROVIDER` is set to `txtcmdr`. Default provider is `engagespark`. |

**Environment variables:**
```
OTP_SMS_PROVIDER=txtcmdr
TXTCMDR_API_URL=
TXTCMDR_API_TOKEN=
```

---

## Summary by Priority

### Must-Have (cannot operate without these)

| # | Service | Category | Paid? |
|---|---------|----------|-------|
| 1 | Laravel Cloud | Hosting | Yes |
| 2 | GitHub | Source code / CI | Free tier available |
| 3 | NetBank Omnipay | Disbursement | Yes (bank agreement) |
| 4 | Email provider | Notifications | Free tier available |
| 5 | Domain registrar | DNS | Yes |

### Should-Have (expected for production use)

| # | Service | Category | Paid? |
|---|---------|----------|-------|
| 6 | WorkOS | SSO, social login, MFA | Free tier available |
| 7 | EngageSpark | SMS notifications | Yes |
| 8 | AWS S3 | File storage | Pay-per-use |
| 9 | NetBank Direct Checkout | Wallet top-up | Yes (bank agreement) |
| 10 | Pusher | Real-time updates | Free tier available |

### Nice-to-Have (enable specific features)

| # | Service | Feature it enables | Paid? |
|---|---------|-------------------|-------|
| 11 | HyperVerge | KYC identity verification | Yes |
| 12 | OpenCage | Location-based redemption | Free tier: 2,500/day |
| 13 | Mapbox | Map snapshots | Free tier: 50,000/month |
| 14 | Cloudflare | Social media previews | Pay-per-use |
| 15 | Telegram Bot | Chat-based operations | Free |
| 16 | Pipedream | SMS shortcode gateway | Free tier available |
| 17 | txtcmdr | Alternative OTP delivery | Yes |

---

*Last updated: March 2026*
