# Redeem-X Documentation

## 🚀 Quick Start

**New?** Start with:
1. [`/WARP.md`](../WARP.md) - Dev environment & commands
2. [Implementation Status](implementation/active/IMPLEMENTATION_STATUS.md) - Current state
3. [Architecture](architecture/) - System design

**For AI:** See `/WARP.md` → Documentation Organization for guidelines on creating docs.

## 📖 Documentation Map

### [Guides](guides/) - How-To Documentation
- **[Features](guides/features/)** - Feature implementation guides
  - [Settlement Envelope User Manual](guides/features/SETTLEMENT_ENVELOPE_USER_MANUAL.md) ⭐ NEW
  - [Bank Integration](guides/features/BANK_INTEGRATION_GUIDE.md), [Feature Flags](guides/features/FEATURE_ENABLEMENT_STRATEGY.md)
  - **Notifications**: [Architecture](guides/features/NOTIFICATION_SYSTEM.md), [Templates](guides/features/NOTIFICATION_TEMPLATES.md), [Triggers & Recipients](guides/features/NOTIFICATION_TRIGGERS_AND_RECIPIENTS.md)
- **[Testing](guides/testing/)** - Test plans & procedures
  - [Testing Plan](guides/testing/TESTING_PLAN.md), [Settlement Testing](guides/testing/SETTLEMENT_TESTING_GUIDE.md)
- **[AI Development](guides/ai-development/)** - AI-assisted dev guides
  - [Form-Flow UI Update SOP](guides/ai-development/FORM_FLOW_UI_UPDATE_SOP.md) ⭐ NEW
  - [PWA-UI Stub Sync SOP](guides/ai-development/PWA_UI_STUB_SYNC_SOP.md) ⭐ NEW
  - [Settlement Envelope Driver Guide](guides/ai-development/SETTLEMENT_ENVELOPE_DRIVER_GUIDE.md)

### [Architecture](architecture/) - System Design
- [Settlement Envelope Architecture](architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md)
- [Driver Composition Architecture](architecture/DRIVER_COMPOSITION_ARCHITECTURE.md) ⭐ NEW - CSV specs, inheritance, bank/pag-ibig dissection
- [Wallet Architecture](architecture/SYSTEM_WALLET_ARCHITECTURE.md)
- [Form Flow System](architecture/FORM_FLOW_SYSTEM.md)
- [Pricing Architecture](architecture/ARCHITECTURE-PRICING.md)

### [API](api/) - API Documentation
- [API Endpoints](api/API_ENDPOINTS.md), [Authentication](api/AUTHENTICATION.md)
- **[Postman Collections](api/postman/)** - API testing

### [Implementation](implementation/) - Plans & TODOs
- **[Active](implementation/active/)** ⭐ Current work
  - [Implementation Status](implementation/active/IMPLEMENTATION_STATUS.md)
  - [Settlement Vouchers TODO](implementation/active/SETTLEMENT_VOUCHERS_TODO.md)
- **[Planned](implementation/planned/)** - Future work
  - [Agentic AI Integration Roadmap](implementation/planned/AGENTIC_AI_INTEGRATION_ROADMAP.md) ⭐ NEW

### [Troubleshooting](troubleshooting/) - Debug Guides
- [Disburse 404](troubleshooting/TROUBLESHOOTING_DISBURSE_404.md)
- [Settlement Rail Debugging](troubleshooting/DEBUGGING_SETTLEMENT_RAIL.md)

### [Decisions](decisions/) - ADRs & Lessons
- [Vue Optional Chaining](decisions/LESSONS_VUE_OPTIONAL_CHAINING.md)
- [KYC Testing Lessons](decisions/TESTING_LESSONS_KYC.md)

### [Completed](completed/) - Historical Docs
- **[Features](completed/features/)** - Completed implementations
- **[Implementations](completed/implementations/)** - Implementation summaries
- **[Sessions](completed/sessions/)** - Dev session notes

### [Reference](reference/) - Technical Specs
- [Data Retention](reference/DATA_RETENTION_POLICY.md), [Security](reference/SECURITY_SPECIFICATION.md)
- **[Whitepapers](reference/whitepapers/)** - Research docs

### [Inbox](inbox/) - Temporary Storage
⚠️ Uncategorized docs - **keep empty**, organize ASAP

## 👨‍💻 For Developers

| Task | Doc |
|------|-----|
| Setup | [`/WARP.md`](../WARP.md) |
| Architecture | [Wallet Architecture](architecture/SYSTEM_WALLET_ARCHITECTURE.md) |
| Payment Integration | [Bank Integration](guides/features/BANK_INTEGRATION_GUIDE.md) |
| API Testing | [Postman](api/postman/) |
| Debug | [Troubleshooting](troubleshooting/) |

## 🔍 For Auditors

- **Security**: [Security Spec](reference/SECURITY_SPECIFICATION.md), [Data Retention](reference/DATA_RETENTION_POLICY.md)
- **Architecture**: [System Design](architecture/)
- **Testing**: [Testing Plan](guides/testing/TESTING_PLAN.md)

## 🤖 For AI Assistants

**Creating docs?** See `/WARP.md` → Documentation Organization section

**Quick guide:**
- Plans → `implementation/planned/`
- Active work → `implementation/active/`
- Completed → `completed/{features,implementations,sessions}/`
- Architecture → `architecture/`
- Guides → `guides/{features,testing,ai-development}/`
- Unsure → `inbox/` (temporary)

## 📂 Structure

```
docs/
├── guides/          # How-tos
├── architecture/    # Design docs
├── api/            # API docs + Postman
├── implementation/ # Plans & TODOs (active/planned)
├── troubleshooting/# Debug guides
├── decisions/      # ADRs & lessons
├── completed/      # Historical (features/implementations/sessions)
├── reference/      # Specs & whitepapers
└── inbox/          # Temporary (keep empty)
```

---

See [`/WARP.md`](../WARP.md) for full dev guide | [`/CHANGELOG.md`](../CHANGELOG.md) for releases