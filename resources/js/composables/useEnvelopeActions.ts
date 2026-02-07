/**
 * @package-candidate settlement-envelope
 * TODO: Extract to packages/settlement-envelope/resources/js/composables/ when API stabilizes
 * See WARP.md "Pending Package Extractions" section
 */
import { ref } from 'vue'
import axios from 'axios'
import type { Envelope, EnvelopeAttachment } from './useEnvelope'

export interface ActionResult {
    success: boolean
    message: string
    envelope?: Partial<Envelope>
    attachment?: Partial<EnvelopeAttachment>
}

export interface UseEnvelopeActionsOptions {
    onSuccess?: (result: ActionResult) => void
    onError?: (error: string) => void
}

export function useEnvelopeActions(voucherCode: string, options: UseEnvelopeActionsOptions = {}) {
    const loading = ref(false)
    const error = ref<string | null>(null)

    const baseUrl = `/api/v1/vouchers/${voucherCode}/envelope`

    // -------------------------------------------------------------------------
    // Helper functions
    // -------------------------------------------------------------------------

    const handleResponse = async <T>(
        promise: Promise<{ data: T & { message: string; envelope?: Partial<Envelope> } }>
    ): Promise<ActionResult> => {
        loading.value = true
        error.value = null

        try {
            const response = await promise
            const result: ActionResult = {
                success: true,
                message: response.data.message,
                envelope: response.data.envelope,
            }
            options.onSuccess?.(result)
            return result
        } catch (err: any) {
            const message = err.response?.data?.message || err.message || 'Action failed'
            error.value = message
            options.onError?.(message)
            return { success: false, message }
        } finally {
            loading.value = false
        }
    }

    // -------------------------------------------------------------------------
    // Status transition actions
    // -------------------------------------------------------------------------

    /**
     * Lock the envelope (transition to LOCKED state).
     * Requires: status = ready_to_settle, settleable = true
     */
    const lock = async (): Promise<ActionResult> => {
        return handleResponse(axios.post(`${baseUrl}/lock`))
    }

    /**
     * Settle the envelope (transition to SETTLED state).
     * Requires: status = locked
     * Note: This may trigger disbursement depending on configuration.
     */
    const settle = async (): Promise<ActionResult> => {
        return handleResponse(axios.post(`${baseUrl}/settle`))
    }

    /**
     * Cancel the envelope (transition to CANCELLED state).
     * Requires: reason (will be logged in audit trail)
     */
    const cancel = async (reason: string): Promise<ActionResult> => {
        return handleResponse(axios.post(`${baseUrl}/cancel`, { reason }))
    }

    /**
     * Reopen a locked envelope (transition to REOPENED state).
     * Requires: status = locked, reason (will be logged in audit trail)
     */
    const reopen = async (reason: string): Promise<ActionResult> => {
        return handleResponse(axios.post(`${baseUrl}/reopen`, { reason }))
    }

    // -------------------------------------------------------------------------
    // Signal actions
    // -------------------------------------------------------------------------

    /**
     * Set a signal value.
     * Requires: envelope status allows editing
     */
    const setSignal = async (key: string, value: boolean): Promise<ActionResult> => {
        return handleResponse(axios.post(`${baseUrl}/signals/${key}`, { value }))
    }

    // -------------------------------------------------------------------------
    // Payload actions
    // -------------------------------------------------------------------------

    /**
     * Update envelope payload with partial data.
     * Requires: envelope status allows editing
     */
    const updatePayload = async (payload: Record<string, any>): Promise<ActionResult> => {
        return handleResponse(axios.patch(`${baseUrl}/payload`, { payload }))
    }

    // -------------------------------------------------------------------------
    // Attachment review actions
    // -------------------------------------------------------------------------

    /**
     * Accept an attachment.
     * Uses envelope ID directly (not voucher code).
     */
    const acceptAttachment = async (envelopeId: number, attachmentId: number): Promise<ActionResult> => {
        loading.value = true
        error.value = null

        try {
            const response = await axios.post(
                `/api/v1/envelopes/${envelopeId}/attachments/${attachmentId}/accept`
            )
            const result: ActionResult = {
                success: true,
                message: response.data.message,
                envelope: response.data.envelope,
                attachment: response.data.attachment,
            }
            options.onSuccess?.(result)
            return result
        } catch (err: any) {
            const message = err.response?.data?.message || err.message || 'Failed to accept attachment'
            error.value = message
            options.onError?.(message)
            return { success: false, message }
        } finally {
            loading.value = false
        }
    }

    /**
     * Reject an attachment with reason.
     * Uses envelope ID directly (not voucher code).
     */
    const rejectAttachment = async (
        envelopeId: number,
        attachmentId: number,
        reason: string
    ): Promise<ActionResult> => {
        loading.value = true
        error.value = null

        try {
            const response = await axios.post(
                `/api/v1/envelopes/${envelopeId}/attachments/${attachmentId}/reject`,
                { reason }
            )
            const result: ActionResult = {
                success: true,
                message: response.data.message,
                envelope: response.data.envelope,
                attachment: response.data.attachment,
            }
            options.onSuccess?.(result)
            return result
        } catch (err: any) {
            const message = err.response?.data?.message || err.message || 'Failed to reject attachment'
            error.value = message
            options.onError?.(message)
            return { success: false, message }
        } finally {
            loading.value = false
        }
    }

    return {
        // State
        loading,
        error,
        // Status transitions
        lock,
        settle,
        cancel,
        reopen,
        // Signals
        setSignal,
        // Payload
        updatePayload,
        // Attachments
        acceptAttachment,
        rejectAttachment,
    }
}
