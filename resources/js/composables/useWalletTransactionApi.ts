import { ref } from 'vue';
import axios from '@/lib/axios';

// Types
export interface WalletTransactionData {
    id: number;
    uuid: string;
    type: 'deposit' | 'withdraw';
    amount: number;
    currency: string;
    confirmed: boolean;
    wallet_id: number;
    // Deposit metadata
    sender_name?: string | null;
    sender_identifier?: string | null;
    payment_method?: string | null;
    deposit_type?: string | null;
    // Withdrawal metadata
    voucher_code?: string | null;
    disbursement?: {
        gateway: string;
        recipient_name: string;
        recipient_identifier: string;
        rail: string;
        status: string;
        transaction_id: string;
    } | null;
    created_at: string;
    updated_at: string;
}

export interface WalletTransactionFilters {
    type?: 'all' | 'deposit' | 'withdraw';
    search?: string;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
}

export interface WalletTransactionListResponse {
    data: WalletTransactionData[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
}

export function useWalletTransactionApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * List wallet transactions with filters and pagination
     */
    const listTransactions = async (filters: WalletTransactionFilters = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.type) params.append('type', filters.type);
            if (filters.search) params.append('search', filters.search);
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);
            if (filters.per_page) params.append('per_page', filters.per_page.toString());
            if (filters.page) params.append('page', filters.page.toString());

            const response = await axios.get<{ data: WalletTransactionListResponse }>(
                `/api/v1/wallet/transactions?${params.toString()}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch wallet transactions';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        listTransactions,
    };
}
