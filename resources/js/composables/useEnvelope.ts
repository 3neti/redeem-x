/**
 * @package-candidate settlement-envelope
 * TODO: Extract to packages/settlement-envelope/resources/js/composables/ when API stabilizes
 * See WARP.md "Pending Package Extractions" section
 */
import { ref, computed } from 'vue'
import axios from 'axios'

// =============================================================================
// ENUMS / TYPES
// =============================================================================

/** All possible envelope statuses (Phase 4 state machine) */
export type EnvelopeStatus = 
    | 'draft'           // Initial state
    | 'in_progress'     // Has payload or attachments
    | 'ready_for_review' // All required items present
    | 'ready_to_settle' // All required accepted + settleable
    | 'locked'          // Frozen, ready for settlement
    | 'settled'         // Terminal: disbursement complete
    | 'cancelled'       // Terminal: hard stop
    | 'rejected'        // Terminal: hard rejection
    | 'reopened'        // Admin reopened a locked envelope

export type ChecklistItemStatus = 'missing' | 'uploaded' | 'needs_review' | 'accepted' | 'rejected'
export type ChecklistItemKind = 'payload_field' | 'document' | 'signal' | 'attestation'
export type ReviewStatus = 'pending' | 'accepted' | 'rejected'

// =============================================================================
// INTERFACES
// =============================================================================

export interface EnvelopeChecklistItem {
    id: number
    key: string
    label: string
    kind: ChecklistItemKind
    status: ChecklistItemStatus
    required: boolean
    doc_type?: string
    payload_pointer?: string
    signal_key?: string
    review_mode?: 'none' | 'manual' | 'auto'
}

export interface EnvelopeAttachment {
    id: number
    doc_type: string
    original_filename: string
    mime_type?: string
    size?: number
    review_status: ReviewStatus
    rejection_reason?: string
    url?: string
    created_at: string
}

export interface EnvelopeSignal {
    id: number
    key: string
    type: string
    value: string
    source: string
    // Driver-defined metadata
    required?: boolean
    signal_category?: 'integration' | 'decision'
    system_settable?: boolean
}

export interface EnvelopeAuditEntry {
    id: number
    action: string
    actor_type?: string
    actor_id?: number
    actor_email?: string
    before?: Record<string, any>
    after?: Record<string, any>
    reason?: string
    created_at: string
}

/** Computed flags from GateEvaluator for state transitions */
export interface EnvelopeComputedFlags {
    // Checklist flags
    required_present: boolean     // All required items != missing
    required_accepted: boolean    // All required items = accepted
    // Signal flags
    blocking_signals: string[]    // Required signals that are false
    all_signals_satisfied: boolean
    // Combined
    settleable: boolean           // Gates pass, ready for lock
}

/** Role-based permissions for envelope actions */
export interface EnvelopePermissions {
    canUpload: boolean       // Provider Staff, Admin
    canReview: boolean       // Reviewer, Admin
    canSetSignals: boolean   // Reviewer, Admin (decision signals only)
    canPatchPayload: boolean // Provider Staff, Admin
    canLock: boolean         // Reviewer, Admin (when ready_to_settle)
    canSettle: boolean       // Reviewer, Admin (when locked)
    canCancel: boolean       // Admin only
    canReopen: boolean       // Admin only (when locked)
    canReject: boolean       // Reviewer, Admin
}

/** Status helper booleans from backend */
export interface EnvelopeStatusHelpers {
    can_edit: boolean
    can_lock: boolean
    can_settle: boolean
    can_cancel: boolean
    can_reject: boolean
    can_reopen: boolean
    is_terminal: boolean
}

export interface Envelope {
    id: number
    reference_code: string
    driver_id: string
    driver_version: string
    status: EnvelopeStatus
    payload: Record<string, any>
    payload_version: number
    context?: Record<string, any>
    gates_cache: Record<string, boolean>
    // Computed flags from GateEvaluator
    computed_flags?: EnvelopeComputedFlags
    // Status helpers from EnvelopeStatus enum
    status_helpers?: EnvelopeStatusHelpers
    // Timestamps
    locked_at?: string
    settled_at?: string
    cancelled_at?: string
    rejected_at?: string
    created_at: string
    updated_at: string
    // Relationships
    checklist_items?: EnvelopeChecklistItem[]
    attachments?: EnvelopeAttachment[]
    signals?: EnvelopeSignal[]
    audit_logs?: EnvelopeAuditEntry[]
}

// =============================================================================
// STATUS CONFIGURATION
// =============================================================================

export interface StatusConfig {
    label: string
    color: 'gray' | 'blue' | 'yellow' | 'green' | 'red' | 'orange' | 'purple'
    variant: 'secondary' | 'default' | 'warning' | 'success' | 'destructive' | 'outline'
}

export const STATUS_CONFIG: Record<EnvelopeStatus, StatusConfig> = {
    draft: { label: 'Draft', color: 'gray', variant: 'secondary' },
    in_progress: { label: 'In Progress', color: 'blue', variant: 'default' },
    ready_for_review: { label: 'Ready for Review', color: 'orange', variant: 'warning' },
    ready_to_settle: { label: 'Ready to Settle', color: 'purple', variant: 'default' },
    locked: { label: 'Locked', color: 'yellow', variant: 'warning' },
    settled: { label: 'Settled', color: 'green', variant: 'success' },
    cancelled: { label: 'Cancelled', color: 'red', variant: 'destructive' },
    rejected: { label: 'Rejected', color: 'red', variant: 'destructive' },
    reopened: { label: 'Reopened', color: 'orange', variant: 'warning' },
}

// =============================================================================
// COMPOSABLE
// =============================================================================

export function useEnvelope(initialEnvelope?: Envelope | null) {
    const envelope = ref<Envelope | null>(initialEnvelope ?? null)
    const loading = ref(false)
    const error = ref<string | null>(null)

    // -------------------------------------------------------------------------
    // Basic computed properties
    // -------------------------------------------------------------------------
    const hasEnvelope = computed(() => envelope.value !== null)
    
    const isSettleable = computed(() => 
        envelope.value?.computed_flags?.settleable ?? 
        envelope.value?.gates_cache?.settleable === true
    )
    
    const checklistProgress = computed(() => {
        if (!envelope.value?.checklist_items) return { total: 0, completed: 0, percentage: 0 }
        
        const items = envelope.value.checklist_items
        const total = items.filter(i => i.required).length
        const completed = items.filter(i => i.required && i.status === 'accepted').length
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0
        
        return { total, completed, percentage }
    })

    // -------------------------------------------------------------------------
    // Status helpers
    // -------------------------------------------------------------------------
    const statusConfig = computed((): StatusConfig => 
        STATUS_CONFIG[envelope.value?.status ?? 'draft']
    )

    const statusColor = computed(() => statusConfig.value.color)

    const isTerminal = computed(() => 
        envelope.value?.status_helpers?.is_terminal ?? 
        ['settled', 'cancelled', 'rejected'].includes(envelope.value?.status ?? '')
    )

    const canEdit = computed(() => 
        envelope.value?.status_helpers?.can_edit ?? 
        !['locked', 'settled', 'cancelled', 'rejected'].includes(envelope.value?.status ?? '')
    )

    const canLock = computed(() => 
        envelope.value?.status_helpers?.can_lock ?? 
        envelope.value?.status === 'ready_to_settle'
    )

    const canSettle = computed(() => 
        envelope.value?.status_helpers?.can_settle ?? 
        envelope.value?.status === 'locked'
    )

    // -------------------------------------------------------------------------
    // Computed flags from GateEvaluator
    // -------------------------------------------------------------------------
    const requiredPresent = computed(() => 
        envelope.value?.computed_flags?.required_present ?? false
    )

    const requiredAccepted = computed(() => 
        envelope.value?.computed_flags?.required_accepted ?? false
    )

    const blockingSignals = computed(() => 
        envelope.value?.computed_flags?.blocking_signals ?? []
    )

    // -------------------------------------------------------------------------
    // API methods
    // -------------------------------------------------------------------------
    const refresh = async (voucherCode: string) => {
        loading.value = true
        error.value = null
        
        try {
            const response = await axios.get(`/api/v1/vouchers/${voucherCode}/envelope`)
            envelope.value = response.data.data
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to load envelope'
        } finally {
            loading.value = false
        }
    }

    return {
        // State
        envelope,
        loading,
        error,
        // Basic computed
        hasEnvelope,
        isSettleable,
        checklistProgress,
        // Status helpers
        statusConfig,
        statusColor,
        isTerminal,
        canEdit,
        canLock,
        canSettle,
        // Computed flags
        requiredPresent,
        requiredAccepted,
        blockingSignals,
        // Methods
        refresh,
    }
}
