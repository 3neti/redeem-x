/**
 * Envelope configuration types for voucher generation and campaigns
 */

export interface PayloadSchema {
    type: string;
    properties?: Record<string, {
        type: string;
        description?: string;
    }>;
    required?: string[];
}

export interface DriverSummary {
    id: string;
    version: string;
    title: string;
    description: string | null;
    domain: string | null;
    documents_count: number;
    checklist_count: number;
    signals_count: number;
    gates_count: number;
    payload_schema: PayloadSchema | null;
}

export interface EnvelopeConfig {
    enabled: boolean;
    driver_id: string;
    driver_version: string;
    initial_payload?: Record<string, any>;
}

export interface EnvelopeConfigCardProps {
    modelValue: EnvelopeConfig | null;
    availableDrivers: DriverSummary[];
    readonly?: boolean;
    showPayloadForm?: boolean;
}

// Re-export existing envelope types for convenience
export type { Envelope, EnvelopeStatus, EnvelopeAttachment } from '@/composables/useEnvelope';
