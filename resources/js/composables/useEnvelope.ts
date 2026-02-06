/**
 * @package-candidate settlement-envelope
 * TODO: Extract to packages/settlement-envelope/resources/js/composables/ when API stabilizes
 * See WARP.md "Pending Package Extractions" section
 */
import { ref, computed } from 'vue'
import axios from 'axios'

export interface EnvelopeChecklistItem {
    id: number
    key: string
    label: string
    kind: 'payload_field' | 'document' | 'signal' | 'attestation'
    status: 'missing' | 'uploaded' | 'needs_review' | 'accepted' | 'rejected'
    required: boolean
    doc_type?: string
    payload_pointer?: string
    signal_key?: string
}

export interface EnvelopeAttachment {
    id: number
    doc_type: string
    original_filename: string
    review_status: 'pending' | 'accepted' | 'rejected'
    created_at: string
}

export interface EnvelopeSignal {
    id: number
    key: string
    type: string
    value: string
    source: string
}

export interface EnvelopeAuditEntry {
    id: number
    action: string
    actor_type?: string
    actor_id?: number
    before?: Record<string, any>
    after?: Record<string, any>
    created_at: string
}

export interface Envelope {
    id: number
    reference_code: string
    driver_id: string
    driver_version: string
    status: 'draft' | 'active' | 'locked' | 'settled' | 'cancelled'
    payload: Record<string, any>
    payload_version: number
    context?: Record<string, any>
    gates_cache: Record<string, boolean>
    locked_at?: string
    settled_at?: string
    cancelled_at?: string
    created_at: string
    updated_at: string
    checklist_items?: EnvelopeChecklistItem[]
    attachments?: EnvelopeAttachment[]
    signals?: EnvelopeSignal[]
    audit_logs?: EnvelopeAuditEntry[]
}

export function useEnvelope(initialEnvelope?: Envelope | null) {
    const envelope = ref<Envelope | null>(initialEnvelope ?? null)
    const loading = ref(false)
    const error = ref<string | null>(null)

    const hasEnvelope = computed(() => envelope.value !== null)
    
    const isSettleable = computed(() => envelope.value?.gates_cache?.settleable === true)
    
    const checklistProgress = computed(() => {
        if (!envelope.value?.checklist_items) return { total: 0, completed: 0, percentage: 0 }
        
        const items = envelope.value.checklist_items
        const total = items.filter(i => i.required).length
        const completed = items.filter(i => i.required && i.status === 'accepted').length
        const percentage = total > 0 ? Math.round((completed / total) * 100) : 0
        
        return { total, completed, percentage }
    })

    const statusColor = computed(() => {
        switch (envelope.value?.status) {
            case 'draft': return 'gray'
            case 'active': return 'blue'
            case 'locked': return 'yellow'
            case 'settled': return 'green'
            case 'cancelled': return 'red'
            default: return 'gray'
        }
    })

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
        envelope,
        loading,
        error,
        hasEnvelope,
        isSettleable,
        checklistProgress,
        statusColor,
        refresh,
    }
}
