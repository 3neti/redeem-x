# 🚀 Redeem-X Ground-Up Implementation Plan

**Date Created**: 2025-11-08  
**Status**: In Progress  
**Project**: Redeem-X UI Facelift & Modular API

---

## 📋 Executive Summary

**Goal**: Build a modern, modular digital voucher platform from scratch without touching the production `x-change` codebase.

**Approach**: Multi-repository architecture with mono-repo package management
- `3neti/redeem-x` - Main umbrella repo (Laravel 12 + Vue 3 + mono-repo packages)
- `3neti/x-change-api` - Backend API (Laravel 12, REST-only) [Future]
- `3neti/x-change-web` - Frontend Web (Vue 3 + Vite + Shadcn UI) [Future]

**Current Phase**: Phase 1 - Repository Setup & Scaffolding

**Timeline**: ~9 weeks (5 phases)

---

## 🏗️ Architecture Overview

### Repository Structure

```
┌──────────────────────────────────────────────────────────────┐
│                         redeem-x                             │
│                  (Main Development Repo)                     │
│                                                              │
│  ┌──────────────────────┐      ┌──────────────────────┐    │
│  │    Laravel 12 App    │      │   Vue 3 + Inertia    │    │
│  │    (Backend API)     │◄────►│     (Frontend)       │    │
│  │  WorkOS + Sanctum    │      │    Shadcn UI         │    │
│  └──────────────────────┘      └──────────────────────┘    │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              packages/lbhurtado/*                     │  │
│  │              (Mono-Repo Packages)                     │  │
│  │                                                        │  │
│  │  • voucher        • wallet         • money-issuer    │  │
│  │  • cash           • contact        • model-channel   │  │
│  │  • model-input    • omnichannel    • payment-gateway │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
│  URL: http://redeem-x.test (via Laravel Herd)              │
└──────────────────────────────────────────────────────────────┘

Future Separation:
┌─────────────────┐          ┌──────────────────┐
│ x-change-api    │          │ x-change-web     │
│ (API Only)      │◄────────►│ (SPA)            │
│ REST + Sanctum  │   API    │ WorkOS + Sanctum │
└─────────────────┘          └──────────────────┘
```

---

## 🛠️ Technology Stack

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Backend** | Laravel 12 | REST API, business logic |
|| | WorkOS AuthKit | Web authentication & SSO |
|| | Sanctum | API token authentication |
|| | Inertia.js | SPA adapter |
|| | Spatie Permissions | Role-based access |
|| | Pest PHP | Testing framework |
| **Frontend** | Vue 3 | Reactive UI framework |
| | TypeScript | Type safety |
| | Vite | Build tool & HMR |
| | Laravel Wayfinder | Type-safe routes |
| **UI** | Shadcn-Vue (reka-ui) | Component library |
| | Tailwind CSS v4 | Utility-first styling |
| | Radix UI | Accessible primitives |
| **Packages** | lbhurtado/voucher | Voucher management |
| | lbhurtado/wallet | Wallet & balance |
| | lbhurtado/money-issuer | Payment gateway abstraction |
| | lbhurtado/payment-gateway | EMI integrations |
| | lbhurtado/cash | Cash transactions |
| | lbhurtado/contact | Contact management |
| **Database** | SQLite (dev) | Development database |
| | PostgreSQL (prod) | Production database |
| **Dev Environment** | Laravel Herd | Local PHP/database server |

---

## 📦 Mono-Repo Package Structure

The `packages/lbhurtado/*` directory contains modular Laravel packages copied from the production `x-change` system:

```
packages/lbhurtado/
├── cash/              # Cash transaction handling
├── contact/           # Contact/user management
├── model-channel/     # Channel abstraction for models
├── model-input/       # Input handling for models
├── money-issuer/      # Payment gateway driver system
├── omnichannel/       # Multi-channel communication
├── payment-gateway/   # EMI integrations (BDO, Maya, LandBank)
├── voucher/           # Core voucher functionality
└── wallet/            # Wallet & balance management
```

### Package Loading Strategy

**In `composer.json`:**
```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./packages/lbhurtado/*",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "lbhurtado/voucher": "@dev",
    "lbhurtado/wallet": "@dev",
    "lbhurtado/money-issuer": "@dev",
    "lbhurtado/payment-gateway": "@dev",
    "lbhurtado/cash": "@dev",
    "lbhurtado/contact": "@dev",
    "lbhurtado/model-channel": "@dev",
    "lbhurtado/model-input": "@dev",
    "lbhurtado/omnichannel": "@dev"
  }
}
```

---

## 🌐 Local Development URLs (Laravel Herd)

| Service | URL | Port |
|---------|-----|------|
| Main App (redeem-x) | http://redeem-x.test | Auto (Herd) |
| Vite HMR | http://localhost:5173 | 5173 |
| Future: x-change-web | http://x-change-web.test | Auto (Herd) |

**Note**: Laravel Herd automatically manages `.test` domains. No manual port configuration needed.

---

## 📅 Implementation Phases

### **Phase 1: Repository Setup & Package Integration** ✅ Current
**Duration**: Week 1-2  
**Status**: In Progress

#### Objectives
1. ✅ Initialize `redeem-x` repository
2. ⬜ Copy packages from `x-change` to `redeem-x/packages/`
3. ⬜ Configure Composer for path repositories
4. ⬜ Install and test package dependencies
5. ⬜ Verify Herd configuration
6. ⬜ Setup database and migrations
7. ⬜ Create base authentication system

#### Detailed Steps

**1.1 Copy Existing Packages**
```bash
# Copy mono-repo packages from x-change
cp -R /Users/rli/PhpstormProjects/x-change/packages/lbhurtado \
      /Users/rli/PhpstormProjects/redeem-x/packages/

# Verify copy
ls -la packages/lbhurtado
```

**1.2 Update Composer Configuration**
```bash
cd /Users/rli/PhpstormProjects/redeem-x

# Add path repositories to composer.json
# (See composer.json section below)

composer update
```

**1.3 Configure Herd**
```bash
# Herd should auto-detect the directory
# Verify it's running at http://redeem-x.test

# If needed, manually link:
herd link redeem-x
```

**1.4 Environment Setup**
```bash
# Copy .env.example to .env (already done by Laravel installer)
# Ensure these settings:
# APP_URL=http://redeem-x.test
# DB_CONNECTION=sqlite
# DB_DATABASE=/Users/rli/PhpstormProjects/redeem-x/database/database.sqlite

# Generate app key (if not done)
php artisan key:generate

# Create SQLite database
touch database/database.sqlite

# Run migrations
php artisan migrate

# Seed initial data
php artisan db:seed
```

**1.5 Test Package Integration**
```bash
# Test that packages are loaded
php artisan tinker

# In tinker:
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Models\Wallet;

# Should not throw errors
```

**Deliverables:**
- ✅ `redeem-x` repository initialized
- ⬜ All 9 packages from `x-change` copied and working
- ⬜ Composer autoloading configured
- ⬜ Database migrated
- ⬜ Herd serving at http://redeem-x.test

---

### **Phase 2: Backend API Development**
**Duration**: Week 3-4  
**Status**: Pending

#### Objectives
1. Create RESTful API controllers
2. Configure hybrid authentication (WorkOS for web, Sanctum for API)
3. Build payment gateway driver system
4. Add API documentation (Scribe)
5. Write API tests (Pest)

#### Key Files to Create
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── V1/
│   │   │       ├── AuthController.php           # Sanctum token issuance
│   │   │       ├── VoucherController.php
│   │   │       ├── VoucherRedeemController.php
│   │   │       ├── WalletController.php
│   │   │       ├── PaymentController.php
│   │   │       └── AuditController.php
│   │   └── Settings/
│   │       ├── ProfileController.php
│   │       ├── PaymentSettingsController.php
│   │       └── ApiTokenController.php          # Manage Sanctum tokens
│   └── Middleware/
│       ├── HandleInertiaRequests.php
│       └── ValidateApiKey.php
└── Services/
    └── MoneyIssuer/
        ├── GatewayManager.php
        └── Facades/MoneyIssuer.php
```

#### API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/token` | Issue Sanctum API token (requires WorkOS session) |
| DELETE | `/api/v1/auth/token` | Revoke current API token |
| GET | `/api/v1/me` | Get authenticated user |
| GET | `/api/v1/vouchers` | List vouchers |
| POST | `/api/v1/vouchers` | Create voucher |
| GET | `/api/v1/vouchers/{id}` | Get voucher details |
| POST | `/api/v1/vouchers/{id}/redeem` | Redeem voucher |
| DELETE | `/api/v1/vouchers/{id}` | Cancel voucher |
| GET | `/api/v1/wallet/balance` | Get wallet balance |
| POST | `/api/v1/wallet/topup` | Top up wallet |
| GET | `/api/v1/wallet/transactions` | List transactions |
| POST | `/api/v1/payments/disburse` | Disburse payment |
| GET | `/api/v1/audit` | Get audit logs |

**Note**: All `/api/v1/*` endpoints require `Authorization: Bearer {token}` header with Sanctum token.

**Deliverables:**
- RESTful API with Sanctum token authentication
- WorkOS session authentication for web routes
- API token management UI in settings
- Payment gateway driver system
- API documentation with authentication examples
- Test coverage >70%

---

### **Phase 3: Frontend UI Development (Shadcn)**
**Duration**: Week 5-7  
**Status**: Pending

#### Objectives
1. Setup Shadcn UI components
2. Build core pages (Dashboard, Vouchers, Wallet, etc.)
3. Implement dark/light mode
4. Add responsive mobile design
5. Integrate with backend API

#### Pages to Build

| Page | Route | Description |
|------|-------|-------------|
| Dashboard | `/dashboard` | Overview, stats, recent activity |
| Generate Voucher | `/vouchers/create` | Create new voucher with QR |
| Voucher History | `/vouchers` | List all vouchers (filterable) |
| Voucher Detail | `/vouchers/{id}` | View voucher details |
| Wallet | `/wallet` | Balance, top-up, transactions |
| Payment Settings | `/settings/payments` | Configure EMI drivers |
| Profile | `/settings/profile` | User profile & branding |
| Appearance | `/settings/appearance` | Theme & display settings |

#### Component Library

```
resources/js/components/
├── ui/                    # Shadcn base components
│   ├── button/
│   ├── card/
│   ├── input/
│   ├── table/
│   ├── dialog/
│   ├── badge/
│   ├── toast/
│   └── ...
├── layout/
│   ├── AppShell.vue
│   ├── AppSidebar.vue
│   ├── AppHeader.vue
│   └── NavMain.vue
├── vouchers/
│   ├── VoucherCard.vue
│   ├── VoucherTable.vue
│   ├── QRCodePreview.vue
│   └── VoucherStatusBadge.vue
├── wallet/
│   ├── BalanceCard.vue
│   ├── TransactionList.vue
│   └── TopUpForm.vue
└── payments/
    ├── GatewaySelector.vue
    └── GatewayConfigForm.vue
```

**Deliverables:**
- 8+ fully functional pages
- Responsive mobile design
- Dark/light mode
- Wayfinder route integration
- API-connected components

---

### **Phase 4: White-Labeling & Partner Support**
**Duration**: Week 8  
**Status**: Pending

#### Objectives
1. Implement partner branding system
2. Create multi-tenant theme support
3. Build partner configuration UI
4. Add branding documentation

#### Branding System

```
public/
└── branding/
    ├── default/
    │   ├── config.json
    │   ├── logo.svg
    │   └── theme.json
    └── {partner-id}/
        ├── config.json
        ├── logo.svg
        └── theme.json
```

**Partner Config Schema:**
```json
{
  "partner_id": "partner-name",
  "name": "Partner Display Name",
  "theme": {
    "primary": "#3b82f6",
    "accent": "#8b5cf6",
    "success": "#10b981",
    "error": "#ef4444"
  },
  "logo": "/branding/partner-name/logo.svg",
  "favicon": "/branding/partner-name/favicon.ico",
  "contact": {
    "email": "support@partner.com",
    "phone": "+63 XXX XXX XXXX"
  }
}
```

**Deliverables:**
- Partner branding system
- Theme loader composable
- Partner configuration UI
- White-label documentation

---

### **Phase 5: Documentation, Testing & Deployment**
**Duration**: Week 9  
**Status**: Pending

#### Objectives
1. Complete all documentation
2. Achieve >70% test coverage
3. Setup CI/CD pipelines
4. Prepare production deployment

#### Documentation Files

```
docs/
├── IMPLEMENTATION_PLAN.md      (This file)
├── PHASE_1_SETUP.md
├── PHASE_2_API.md
├── PHASE_3_FRONTEND.md
├── PHASE_4_WHITELABEL.md
├── PHASE_5_DEPLOYMENT.md
├── ARCHITECTURE.md
├── API_REFERENCE.md
├── PACKAGE_DEVELOPMENT.md
├── DEPLOYMENT_GUIDE.md
└── WHITE_LABEL_GUIDE.md
```

**Deliverables:**
- Complete documentation suite
- Test coverage >70%
- CI/CD pipelines configured
- Production deployment guide
- Partner onboarding docs

---

## 🔧 Development Workflow

### Daily Development

**Start Development Server:**
```bash
cd /Users/rli/PhpstormProjects/redeem-x

# Terminal 1: Laravel (via Herd - already running)
# Access: http://redeem-x.test

# Terminal 2: Vite HMR
npm run dev

# Terminal 3: Queue Worker (if needed)
php artisan queue:listen

# Terminal 4: Log Viewer (optional)
php artisan pail --timeout=0
```

**Or use the all-in-one command:**
```bash
composer dev
```

### Testing

```bash
# Run all tests
composer test
# or
php artisan test

# Run specific test file
php artisan test tests/Feature/VoucherTest.php

# Run with coverage
php artisan test --coverage

# Frontend tests
npm run test:unit
```

### Code Quality

```bash
# PHP formatting
./vendor/bin/pint

# TypeScript/Vue linting
npm run lint

# TypeScript/Vue formatting
npm run format
```

---

## 📊 Progress Tracking

### Phase 1 Checklist

- [x] Repository initialized (`redeem-x`)
- [ ] Copy packages from `x-change/packages/lbhurtado/*`
- [ ] Configure Composer path repositories
- [ ] Install package dependencies
- [ ] Verify Herd configuration (http://redeem-x.test)
- [ ] Create SQLite database
- [ ] Run migrations from packages
- [ ] Seed initial data
- [ ] Test package integration
- [ ] Document package APIs
- [ ] Create WARP.md (already exists ✅)

### Overall Progress

| Phase | Status | Start Date | End Date | Progress |
|-------|--------|-----------|----------|----------|
| 1. Setup & Packages | 🟡 In Progress | 2025-11-08 | TBD | 20% |
| 2. Backend API | ⬜ Pending | TBD | TBD | 0% |
| 3. Frontend UI | ⬜ Pending | TBD | TBD | 0% |
| 4. White-Label | ⬜ Pending | TBD | TBD | 0% |
| 5. Documentation | ⬜ Pending | TBD | TBD | 0% |

---

## 🎯 Success Criteria

- [ ] All packages from `x-change` working in `redeem-x`
- [ ] Hybrid authentication (WorkOS for web, Sanctum for API)
- [ ] Payment gateway driver system supporting 4+ EMIs
- [ ] Complete Shadcn UI with dark/light mode
- [ ] Mobile-responsive design
- [ ] Partner white-labeling functional
- [ ] Test coverage >70%
- [ ] Complete documentation suite
- [ ] Production deployment successful

---

## 📝 Notes & Decisions

### 2025-11-08 - Initial Planning
- Using Laravel Herd for local development (http://redeem-x.test)
- Starting with mono-repo approach in `redeem-x`
- Packages will be copied exactly from `x-change/packages/lbhurtado/*`
- Future split into `x-change-api` and `x-change-web` repos planned
- Using Laravel 12 + Vue 3 + Inertia + Wayfinder stack
- Shadcn UI (reka-ui) for component library

### Key Architecture Decisions
1. **Mono-repo first**: Keep all packages in `packages/` for easier development
2. **Herd for local dev**: No manual Apache/Nginx configuration needed
3. **SQLite for dev**: Fast, portable, no external database needed
4. **Hybrid authentication**: WorkOS for web sessions, Sanctum for API tokens
5. **Pest for testing**: Modern PHP testing framework
6. **Wayfinder for routes**: Type-safe route generation from controllers
7. **Inertia.js**: SPA experience without API boilerplate

---

## 🔗 Related Documentation

- [WARP.md](../WARP.md) - Warp AI development guidelines
- [README.md](../README.md) - Project overview
- Phase documentation (to be created):
  - `PHASE_1_SETUP.md`
  - `PHASE_2_API.md`
  - `PHASE_3_FRONTEND.md`
  - `PHASE_4_WHITELABEL.md`
  - `PHASE_5_DEPLOYMENT.md`

---

**Last Updated**: 2025-11-08  
**Next Review**: Phase 1 completion  
**Maintained by**: 3neti R&D OPC / Redeem-X Team
