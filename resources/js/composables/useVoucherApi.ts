import { ref } from 'vue';
import axios from '@/lib/axios';
import { useApiError, type ApiError } from './useApiError';

export interface VoucherData {
    code: string;
    status: string;
    amount: number;
    currency: string;
    created_at: string;
    expires_at?: string;
    redeemed_at?: string;
    starts_at?: string;
    is_expired: boolean;
    is_redeemed: boolean;
    can_redeem: boolean;
    owner?: {
        name: string;
        email: string;
    };
}

export interface PaginatedVouchers {
    data: VoucherData[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
    };
    filters?: {
        status?: string;
        search?: string;
    };
}

export interface GenerateVouchersRequest {
    amount: number;
    count: number;
    prefix?: string;
    mask?: string;
    ttl_days?: number;
    input_fields?: string[];
    validation_secret?: string;
    validation_mobile?: string;
    feedback_email?: string;
    feedback_mobile?: string;
    feedback_webhook?: string;
    rider_message?: string;
    rider_url?: string;
    rider_redirect_timeout?: number;
    rider_splash?: string;
    rider_splash_timeout?: number;
    preview_enabled?: boolean;
    preview_scope?: string;
    preview_message?: string;
}

export interface GenerateVouchersResponse {
    count: number;
    vouchers: VoucherData[];
    total_amount: number;
    currency: string;
}

export interface BulkVoucherItem {
    mobile?: string;
    external_metadata?: {
        external_id?: string;
        external_type?: string;
        reference_id?: string;
        user_id?: string;
        custom?: Record<string, any>;
    };
}

export interface BulkCreateVouchersRequest {
    campaign_id: number;
    vouchers: BulkVoucherItem[];
}

export interface BulkCreateVouchersResponse {
    count: number;
    vouchers: VoucherData[];
    total_amount: number;
    currency: string;
    errors?: Array<{
        index: number;
        mobile?: string;
        error: string;
    }>;
}

export function useVoucherApi() {
    const loading = ref(false);
    const error = ref<ApiError | null>(null);
    const { handleError } = useApiError();

    const listVouchers = async (params?: {
        per_page?: number;
        status?: string;
        search?: string;
        page?: number;
    }): Promise<PaginatedVouchers | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get('/api/v1/vouchers', { params });
            return response.data.data;
        } catch (err) {
            error.value = handleError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    const showVoucher = async (code: string): Promise<VoucherData | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/v1/vouchers/${code}`);
            return response.data.data.voucher;
        } catch (err) {
            error.value = handleError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    const generateVouchers = async (
        data: GenerateVouchersRequest
    ): Promise<GenerateVouchersResponse | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/v1/vouchers', data);
            return response.data.data;
        } catch (err) {
            error.value = handleError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    const cancelVoucher = async (code: string): Promise<boolean> => {
        loading.value = true;
        error.value = null;

        try {
            await axios.delete(`/api/v1/vouchers/${code}`);
            return true;
        } catch (err) {
            error.value = handleError(err);
            return false;
        } finally {
            loading.value = false;
        }
    };

    const bulkCreateVouchers = async (
        data: BulkCreateVouchersRequest
    ): Promise<BulkCreateVouchersResponse | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post('/api/v1/vouchers/bulk-create', data);
            return response.data.data;
        } catch (err) {
            error.value = handleError(err);
            return null;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        listVouchers,
        showVoucher,
        generateVouchers,
        cancelVoucher,
        bulkCreateVouchers,
    };
}
