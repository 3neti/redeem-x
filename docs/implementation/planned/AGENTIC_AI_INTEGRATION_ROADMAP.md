# Agentic AI Integration Roadmap for Settlement Envelope

## Overview

This roadmap outlines how to integrate agentic AI systems with the Settlement Envelope to enable intelligent, event-driven signal automation.

## Current State

**Events exist but don't broadcast:**
- 9 domain events: `EnvelopeCreated`, `PayloadUpdated`, `AttachmentUploaded`, `AttachmentReviewed`, `SignalChanged`, `GateChanged`, `EnvelopeLocked`, `EnvelopeSettled`, `EnvelopeCancelled`
- Events use `Dispatchable`, `InteractsWithSockets`, `SerializesModels` traits
- Events do NOT implement `ShouldBroadcast` — internal only
- Broadcasting infrastructure ready (Pusher configured in `.env.example`)

**Signals support both manual and system sources:**
```yaml
signals:
  definitions:
    - key: "joint_income_sufficient"
      source: "host"              # Manual (current)
      # source: "system"          # Automated (future)
      signal_category: "decision" # Human
      # signal_category: "integration" # System
```

## Agentic AI Architecture

### Event-Driven Pattern (Not API-Based)

```
┌─────────────────┐     broadcast      ┌─────────────────┐
│ Settlement      │ ─────────────────► │ Event Bus       │
│ Envelope        │                    │ (Pusher/Reverb) │
└─────────────────┘                    └────────┬────────┘
                                                │
                                                │ subscribe
                                                ▼
                                       ┌─────────────────┐
                                       │ AI Agent        │
                                       │ (External)      │
                                       └────────┬────────┘
                                                │
                                                │ webhook callback
                                                ▼
                                       ┌─────────────────┐
                                       │ Signal Endpoint │
                                       │ POST /api/v1/   │
                                       │ envelopes/{id}/ │
                                       │ signals/{key}   │
                                       └─────────────────┘
```

### Why Event-Based?

1. **Async by nature** — AI analysis takes time (seconds to minutes)
2. **Scalable** — Multiple agents can subscribe to same events
3. **Decoupled** — AI agent can be external service, Lambda, or local
4. **Retry-friendly** — Failed analysis doesn't block envelope flow

## Implementation Phases

### Phase 1: Broadcast Infrastructure

**Goal:** Make envelope events available to external systems

**Changes:**
1. Add `ShouldBroadcast` interface to envelope events
2. Define channel: `envelope.{envelope_id}` (private channel)
3. Add event payload serialization for AI consumption
4. Configure Pusher/Reverb for production

**Example Event Update:**
```php
class AttachmentUploaded implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('envelope.' . $this->envelope->id),
            new PrivateChannel('ai-agent.documents'),  // AI listens here
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'envelope_id' => $this->envelope->id,
            'reference_code' => $this->envelope->reference_code,
            'doc_type' => $this->attachment->doc_type,
            'file_url' => $this->attachment->getTemporaryUrl(60),
            'metadata' => $this->attachment->metadata,
            'driver_id' => $this->envelope->driver_id,
        ];
    }
}
```

### Phase 2: AI Agent Webhook Endpoint

**Goal:** Allow AI agents to set signals programmatically

**New API Endpoint:**
```
POST /api/v1/envelopes/{id}/signals/{key}
Authorization: Bearer {ai_agent_token}
Content-Type: application/json

{
    "value": true,
    "confidence": 0.95,
    "reasoning": "Combined monthly income ₱85,000 exceeds 3x amortization ₱24,000",
    "extracted_data": {
        "borrower_income": 50000,
        "co_borrower_income": 35000,
        "monthly_amortization": 24000
    },
    "model_version": "gpt-4-vision-2024-01"
}
```

**Signal Metadata Storage:**

Extend `EnvelopeSignal` model to store AI metadata:
```php
// New columns: confidence, reasoning, extracted_data, model_version, set_by_type
$signal->update([
    'value' => true,
    'confidence' => 0.95,
    'reasoning' => '...',
    'set_by_type' => 'ai_agent',  // vs 'user' or 'system'
]);
```

### Phase 3: Signal Source Types

**Goal:** Distinguish between human, system, and AI-assisted signals

**New Signal Sources:**
```yaml
signals:
  definitions:
    - key: "joint_income_sufficient"
      source: "ai_assisted"           # NEW
      signal_category: "integration"
      system_settable: true
      requires_confirmation: false    # Auto-accept if confidence >= threshold
      confidence_threshold: 0.90      # Require human review if < 90%
```

**Source Hierarchy:**
- `host` — Human must set via UI
- `system` — Internal system (webhooks, integrations)
- `ai_assisted` — AI agent sets, may require confirmation

### Phase 4: Confirmation Workflow (Optional)

**Goal:** Human-in-the-loop for low-confidence AI decisions

**Flow:**
```
AI sets signal with confidence=0.75
    ↓
Signal marked "pending_confirmation"
    ↓
UI shows: "AI suggests: ✓ Joint income sufficient (75% confidence)"
    ↓
Human clicks Confirm or Reject
    ↓
Signal finalized, gate re-evaluated
```

**UI Changes:**
- Show AI reasoning in signal card
- Highlight signals needing confirmation
- Display confidence score and extracted data

### Phase 5: Document Analysis Agent

**Goal:** AI agent that analyzes uploaded documents

**Agent Capabilities:**
1. **OCR/Extraction** — Read income from payslips, ITR
2. **Validation** — Check document authenticity signals
3. **Classification** — Identify document type if mislabeled
4. **Comparison** — Cross-reference data across documents

**Trigger Events:**
- `AttachmentUploaded` — Analyze new document
- `PayloadUpdated` — Re-validate against documents

**Output Signals:**
- `income_validated` — Income docs match stated amounts
- `document_authentic` — No tampering detected
- `data_consistent` — Documents agree with each other

## Implementation Checklist

### Phase 1: Broadcast Infrastructure
- [ ] Add `ShouldBroadcast` to envelope events
- [ ] Define broadcast channels and payloads
- [ ] Add temporary URL generation for attachments
- [ ] Configure Pusher/Reverb credentials
- [ ] Test event broadcasting

### Phase 2: AI Agent Webhook
- [ ] Create `POST /api/v1/envelopes/{id}/signals/{key}` endpoint
- [ ] Add AI agent authentication (API tokens)
- [ ] Extend `EnvelopeSignal` with metadata columns
- [ ] Store confidence, reasoning, extracted_data
- [ ] Audit log AI signal changes

### Phase 3: Signal Source Types
- [ ] Add `ai_assisted` source type to DriverData
- [ ] Add `requires_confirmation`, `confidence_threshold` to signal schema
- [ ] Update GateEvaluator to handle pending confirmations
- [ ] Add `set_by_type` to signal model

### Phase 4: Confirmation Workflow
- [ ] Add `pending_confirmation` status to signals
- [ ] Create confirmation UI components
- [ ] Add `confirm` / `reject` API endpoints
- [ ] Update checklist to show AI suggestions

### Phase 5: Document Analysis Agent
- [ ] Define agent interface/contract
- [ ] Implement document URL signing
- [ ] Create agent subscription to events
- [ ] Build income extraction logic
- [ ] Add confidence scoring

## File Changes Summary

**Package (settlement-envelope):**
- `src/Events/*.php` — Add `ShouldBroadcast`, `broadcastWith()`
- `src/Models/EnvelopeSignal.php` — Add metadata columns
- `src/Data/SignalDefinitionData.php` — Add new properties
- `database/migrations/` — Add signal metadata columns

**Host App:**
- `app/Http/Controllers/Api/EnvelopeSignalController.php` — AI webhook
- `routes/api.php` — Signal endpoint
- `resources/js/components/envelope/` — Confirmation UI

**New (External or Lambda):**
- AI Agent service subscribing to Pusher events
- Document analysis pipeline

## Security Considerations

1. **AI Agent Tokens** — Separate from user tokens, scoped to signal setting
2. **Rate Limiting** — Prevent runaway AI from spamming signals
3. **Audit Trail** — Full logging of AI decisions with reasoning
4. **Rollback** — Ability to revert AI-set signals
5. **Confidence Floor** — Minimum confidence required to set signal

## Related Documentation

- [Settlement Envelope Architecture](../../architecture/SETTLEMENT_ENVELOPE_ARCHITECTURE.md)
- [Driver Composition Architecture](../../architecture/DRIVER_COMPOSITION_ARCHITECTURE.md)
- [Settlement Envelope Driver Guide](../../guides/ai-development/SETTLEMENT_ENVELOPE_DRIVER_GUIDE.md)
