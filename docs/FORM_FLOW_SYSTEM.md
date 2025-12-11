# Fully Autonomous Form Flow System - Multi-Step Input Collection

## Problem Statement

We need a fully autonomous, reusable multi-step form collection system that:
* Works as a standalone micro-system embedded in host applications
* Extracts current controller logic (selfie, location, signature, KYC) into reusable packages
* Has its own routing, session management, and navigation - completely isolated from host app
* Transforms domain-specific instructions (VoucherInstructionsData) into generic form flow instructions
* Can be used for ANY multi-step input collection: redemption, onboarding, KYC verification, surveys, loan applications, etc.

## Architecture Overview

### Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│ Host App (redeem-x)                                             │
│                                                                  │
│  VoucherInstructionsData (domain-specific)                      │
│         │                                                        │
│         ▼                                                        │
│  Driver (YAML/JSON Config)                                      │
│         │                                                        │
│         ▼                                                        │
│  FormFlowInstructionsData (generic interface)                   │
│  {                                                               │
│    flow_id: 'voucher_abc123',                                   │
│    steps: [{handler: 'location'}, {handler: 'selfie'}],         │
│    callbacks: {on_complete: '/api/redeem/finalize'}             │
│  }                                                               │
│         │                                                        │
│         ▼                                                        │
│  POST /form-flow/start                                          │
│                                                                  │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────┐
│ Form Flow Manager (AUTONOMOUS SYSTEM)                           │
│                                                                  │
│  Own Routes:   /form-flow/{flow_id}/*                           │
│  Own Session:  form_flow.{flow_id}.*                            │
│  Own Logic:    Navigation, validation, state management         │
│                                                                  │
│  Handlers (extracted from current controllers):                 │
│  ├─ form-handler-location   (PHP + Vue)                         │
│  ├─ form-handler-selfie     (PHP + Vue)                         │
│  ├─ form-handler-signature  (PHP + Vue)                         │
│  └─ form-handler-kyc        (PHP + Vue)                         │
│                                                                  │
│  Collected Data → JSON                                          │
└─────────────────────────┬───────────────────────────────────────┘
                          │
                          ▼ POST {callback_url}
┌─────────────────────────────────────────────────────────────────┐
│ Host App (redeem-x)                                             │
│                                                                  │
│  POST /api/redeem/{voucher}/finalize                            │
│  Receives: { location: {...}, selfie: 'base64...', ... }       │
│  Resumes: Voucher redemption with collected data                │
└─────────────────────────────────────────────────────────────────┘
```

## Core Components

### 1. Driver-Based Mapping System (DirXML-Style)

Inspired by DirXML driver architecture, the transformation from domain-specific data to generic form flow instructions is driven by declarative YAML configuration files.

See detailed implementation in Implementation Plan section below.

### 2. FormFlowInstructionsData (Generic Interface)

Generic data transfer object with no domain knowledge.

### 3. Form Flow Manager Service

Manages flow state, navigation, and callbacks.

### 4. Form Handler Interface

Contract for all input handlers (location, selfie, signature, KYC, etc.).

## Implementation Plan

### Phase 0: Create Driver/Mapping System ✅ COMPLETE
1. Install Symfony YAML: `composer require symfony/yaml`
2. Create `packages/form-flow-manager/src/Data/DriverConfigData.php`
3. Create `packages/form-flow-manager/src/Services/TemplateRenderer.php`
4. Create `packages/form-flow-manager/src/Services/ExpressionEvaluator.php`
5. Create `packages/form-flow-manager/src/Services/MappingEngine.php`
6. Create `packages/form-flow-manager/src/Services/DriverRegistry.php`
7. Create voucher driver: `config/form-flow-drivers/voucher-redemption.yaml`
8. Add comprehensive tests for mapping engine
9. Create example drivers: loan-application.yaml, kyc-verification.yaml

### Phase 1: Create Core Manager Package ✅ COMPLETE
- FormFlowInstructionsData & FormFlowStepData (generic DTOs)
- FormHandlerInterface contract
- FormFlowService (session-based state management)
- FormFlowController (REST API)
- FormFlowServiceProvider (Laravel integration)
- 54 tests passing, 113 assertions

### Phase 2: Location Handler Package 🔄 IN PROGRESS
See detailed guide: `docs/PHASE_2_LOCATION_HANDLER.md`

### Phase 3-5: Extract Remaining Handler Packages
- Phase 3: Selfie Handler  
- Phase 4: Signature Handler
- Phase 5: KYC Handler (HyperVerge integration)

Each follows the same pattern as Location Handler.

### Phase 6: Finalize Driver Configuration
Complete and validate all driver configurations.

### Phase 7: Integrate with Host App
Wire up the form flow system to the voucher redemption flow.

### Phase 8: Deprecate Old System
Feature flag and gradual rollout.

### Phase 9: Testing & Validation
Comprehensive integration and performance testing.

## Benefits

1. **Complete isolation**: Form flow has zero dependencies on host app internals
2. **True reusability**: Can be dropped into ANY Laravel app
3. **Clean contracts**: Interface-based design with clear boundaries
4. **Extracted knowledge**: Current controller logic preserved and packaged
5. **Testability**: Each handler can be tested independently
6. **Declarative mapping**: Driver-based transformation (DirXML-style)
7. **Zero code changes**: Add new mappings via YAML/JSON
8. **Version control**: Driver configs tracked in git
9. **Self-documenting**: YAML config IS the transformation documentation

## Key Design Principles

1. **Generic interface**: `FormFlowInstructionsData` has no domain knowledge
2. **Autonomous routing**: `/form-flow/*` routes completely separate from host
3. **Isolated session**: `form_flow.{flow_id}.*` keys never collide with host
4. **Callback pattern**: Host app receives results via HTTP callback
5. **Stateless handlers**: Each handler only knows its own logic
6. **Driver-based transformation**: Declarative YAML/JSON mappings (DirXML pattern)
7. **Package per handler**: Complete isolation including frontend code
8. **Configuration over code**: Transformations defined in config files

## Current Status

**✅ Phase 0 Complete**: Driver/mapping system (2,514 lines, 47 tests)
**✅ Phase 1 Complete**: Core Manager Package (948 lines, 54 tests total)
**🔄 Phase 2 In Progress**: Location Handler Package (see `PHASE_2_LOCATION_HANDLER.md`)

## Implementation Guides

- **Phase 0 & 1**: This document (architecture & core system)
- **Phase 2**: `PHASE_2_LOCATION_HANDLER.md` (detailed step-by-step)
- **Phase 3-5**: TBD (will follow Phase 2 pattern)

For complete implementation details, refer to these documents.
