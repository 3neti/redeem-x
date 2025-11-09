import { ref } from 'vue';
import axios from '@/lib/axios';

// Types
export interface TransactionData {
    code: string;
    amount: number;
    currency: string;
    status: string;
    redeemed_at: string;
    created_at: string;
}

export interface TransactionStats {
    total: number;
    total_amount: number;
    today: number;
    this_month: number;
    currency: string;
}

export interface TransactionFilters {
    search?: string;
    date_from?: string;
    date_to?: string;
    per_page?: number;
    page?: number;
}

export interface TransactionListResponse {
    data: TransactionData[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    filters: TransactionFilters;
}

export interface TransactionStatsResponse {
    stats: TransactionStats;
}

export interface TransactionDetailResponse {
    transaction: TransactionData;
    redemption_count: number;
}

export function useTransactionApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * List transactions with filters and pagination
     */
    const listTransactions = async (filters: TransactionFilters = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.search) params.append('search', filters.search);
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);
            if (filters.per_page) params.append('per_page', filters.per_page.toString());
            if (filters.page) params.append('page', filters.page.toString());

            const response = await axios.get<{ data: TransactionListResponse }>(
                `/api/v1/transactions?${params.toString()}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch transactions';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Get transaction statistics
     */
    const getStats = async (filters: Pick<TransactionFilters, 'date_from' | 'date_to'> = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);

            const response = await axios.get<{ data: TransactionStatsResponse }>(
                `/api/v1/transactions/stats?${params.toString()}`
            );

            return response.data.data.stats;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch stats';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Export transactions as CSV
     */
    const exportTransactions = async (filters: Pick<TransactionFilters, 'search' | 'date_from' | 'date_to'> = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.search) params.append('search', filters.search);
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);

            // For file downloads, we need to handle it differently
            window.location.href = `/api/v1/transactions/export?${params.toString()}`;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to export transactions';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Show transaction details
     */
    const showTransaction = async (code: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get<{ data: TransactionDetailResponse }>(
                `/api/v1/transactions/${code}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch transaction details';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        listTransactions,
        getStats,
        exportTransactions,
        showTransaction,
    };
}
