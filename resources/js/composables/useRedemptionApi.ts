import { ref } from 'vue';
import axios from '@/lib/axios';

// Types
export interface VoucherInfo {
    code: string;
    amount: number;
    currency: string;
    expires_at?: string;
    status: string;
}

export interface ValidationRequirements {
    secret?: boolean;
    mobile?: boolean;
    inputs?: string[];
}

export interface ValidateVoucherResponse {
    voucher: VoucherInfo;
    can_redeem: boolean;
    required_validation: ValidationRequirements;
    required_inputs: string[];
}

export interface RedeemVoucherRequest {
    code: string;
    mobile: string;
    country?: string;
    secret?: string;
    bank_code?: string;
    account_number?: string;
    inputs?: Record<string, any>;
}

export interface RedeemVoucherResponse {
    message: string;
    voucher: VoucherInfo;
    rider?: {
        message?: string;
        url?: string;
    };
}

export function useRedemptionApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * Validate voucher code and get redemption requirements
     */
    const validateVoucher = async (code: string): Promise<ValidateVoucherResponse> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post<{ data: ValidateVoucherResponse }>(
                '/api/v1/redeem/validate',
                { code }
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to validate voucher';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Redeem voucher with wallet information
     */
    const redeemVoucher = async (request: RedeemVoucherRequest): Promise<RedeemVoucherResponse> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post<{ data: RedeemVoucherResponse }>(
                '/api/v1/redeem/wallet',
                request
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to redeem voucher';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        validateVoucher,
        redeemVoucher,
    };
}
