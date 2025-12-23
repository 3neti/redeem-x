/**
 * TypeScript types for voucher instructions
 * Matches PHP VoucherInstructionsData and related DTOs
 */

export enum VoucherInputField {
    EMAIL = 'email',
    MOBILE = 'mobile',
    REFERENCE_CODE = 'reference_code',
    SIGNATURE = 'signature',
    KYC = 'kyc',
    NAME = 'name',
    ADDRESS = 'address',
    BIRTH_DATE = 'birth_date',
    GROSS_MONTHLY_INCOME = 'gross_monthly_income',
    LOCATION = 'location',
    OTP = 'otp',
}

export interface VoucherInputFieldOption {
    label: string;
    value: VoucherInputField;
}

export interface CashValidation {
    secret?: string | null;
    mobile?: string | null;
    country?: string | null;
    location?: string | null;
    radius?: string | null;
}

export interface CashInstruction {
    amount: number;
    currency: string;
    validation: CashValidation;
    settlement_rail?: string | null; // 'INSTAPAY' | 'PESONET' | null (auto)
    fee_strategy?: string; // 'absorb' | 'include' | 'add'
}

export interface InputFields {
    fields: VoucherInputField[];
}

export interface FeedbackInstruction {
    mobile?: string | null;
    email?: string | null;
    webhook?: string | null;
}

export interface RiderInstruction {
    message?: string | null;
    url?: string | null;
    redirect_timeout?: number | null;
    splash?: string | null;
    splash_timeout?: number | null;
}
    splash_timeout?: number | null;
}

export interface VoucherInstructions {
    cash: CashInstruction;
    inputs: InputFields;
    feedback: FeedbackInstruction;
    rider: RiderInstruction;
    count: number;
    prefix?: string | null;
    mask?: string | null;
    ttl?: string | null; // ISO-8601 duration format (e.g., "P1D", "PT12H")
}

export interface VoucherGenerationForm {
    // Basic settings
    amount: number;
    count: number;
    prefix?: string;
    mask?: string;
    ttl_days?: number | null;

    // Input fields
    input_fields: VoucherInputField[];

    // Validation
    validation_secret?: string;
    validation_mobile?: string;
    validation_location?: string;
    validation_radius?: string;

    // Feedback channels
    feedback_email?: string;
    feedback_mobile?: string;
    feedback_webhook?: string;

    // Rider
    rider_message?: string;
    rider_url?: string;
}

export interface CostBreakdown {
    base_charge: number; // Face value to escrow
    service_fee: number; // 1% for high-value vouchers
    expiry_fee: number; // ₱10 for >90 days
    premium_fee: number; // ₱5 for feedback/rider
    total: number;
}

export interface Voucher {
    id: number;
    code: string;
    batch_id?: string | null;
    owner_id: number;
    owner_type: string;
    amount: number;
    currency: string;
    status: 'active' | 'redeemed' | 'expired' | 'cancelled';
    metadata: {
        instructions: VoucherInstructions;
        secret?: string;
    };
    starts_at?: string | null;
    expires_at?: string | null;
    redeemed_at?: string | null;
    created_at: string;
    updated_at: string;
}

export interface VoucherGenerationResult {
    vouchers: Voucher[];
    batch_id: string;
    count: number;
    total_cost: number;
    message: string;
}

// Inspect API types
export interface RequiredInput {
    value: string;
    label: string;
}

export interface LocationValidation {
    required: boolean;
    target_lat: number;
    target_lng: number;
    radius_meters: number;
    on_failure: 'block' | 'warn';
    description: string;
}

export interface TimeWindow {
    start_time: string;
    end_time: string;
    timezone: string;
}

export interface TimeValidation {
    window: TimeWindow | null;
    limit_minutes: number | null;
    description: string;
}

export interface InspectInstructions {
    amount?: number; // optional when scope = requirements_only/none
    currency?: string; // optional
    formatted_amount?: string; // optional
    required_inputs?: RequiredInput[];
    expires_at?: string | null;
    starts_at?: string | null;
    validation?: {
        has_secret?: boolean;
        is_assigned?: boolean;
        assigned_mobile_masked?: string | null;
    };
    location_validation?: LocationValidation;
    time_validation?: TimeValidation;
    rider?: {
        message?: string;
        url?: string;
    };
}

export interface PreviewPolicy {
    enabled: boolean;
    scope: 'full' | 'requirements_only' | 'none';
    message?: string;
}

export interface InspectResponse {
    success: boolean;
    code: string;
    status: 'active' | 'redeemed' | 'expired' | 'scheduled';
    metadata: any;
    info: any;
    preview?: PreviewPolicy;
    instructions?: InspectInstructions;
}
