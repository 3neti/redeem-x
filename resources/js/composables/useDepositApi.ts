import { ref } from 'vue';
import axios from '@/lib/axios';

// Types
export interface SenderData {
    id: number;
    mobile: string;
    name: string;
    total_sent: number;
    transaction_count: number;
    institutions_used: string[];
    latest_institution: string | null;
    latest_institution_name: string | null;
    first_transaction_at: string | null;
    last_transaction_at: string | null;
}

export interface DepositTransactionData {
    sender_id: number;
    sender_name: string;
    sender_mobile: string;
    amount: number;
    currency: string;
    institution: string;
    institution_name: string;
    operation_id: string | null;
    channel: string | null;
    reference_number: string | null;
    transfer_type: string | null;
    timestamp: string | null;
}

export interface DepositStats {
    total: number;
    total_amount: number;
    today: number;
    this_month: number;
    unique_senders: number;
    currency: string;
}

export interface DepositFilters {
    search?: string;
    date_from?: string;
    date_to?: string;
    institution?: string;
    per_page?: number;
    page?: number;
}

export interface SenderFilters {
    search?: string;
    sort_by?: 'last_transaction_at' | 'total_sent' | 'transaction_count';
    sort_order?: 'asc' | 'desc';
    per_page?: number;
    page?: number;
}

export interface DepositListResponse {
    data: DepositTransactionData[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    filters: DepositFilters;
}

export interface SenderListResponse {
    data: SenderData[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    filters: SenderFilters;
}

export interface SenderDetailResponse {
    sender: SenderData;
    transactions: DepositTransactionData[];
}

export interface DepositStatsResponse {
    stats: DepositStats;
}

export function useDepositApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * List deposits with filters and pagination
     */
    const listDeposits = async (filters: DepositFilters = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.search) params.append('search', filters.search);
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);
            if (filters.institution) params.append('institution', filters.institution);
            if (filters.per_page) params.append('per_page', filters.per_page.toString());
            if (filters.page) params.append('page', filters.page.toString());

            const response = await axios.get<{ data: DepositListResponse }>(
                `/api/v1/deposits?${params.toString()}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch deposits';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Get deposit statistics
     */
    const getDepositStats = async (filters: Pick<DepositFilters, 'date_from' | 'date_to' | 'institution'> = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);
            if (filters.institution) params.append('institution', filters.institution);

            const response = await axios.get<{ data: DepositStatsResponse }>(
                `/api/v1/deposits/stats?${params.toString()}`
            );

            return response.data.data.stats;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch deposit stats';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * List sender contacts with filters and pagination
     */
    const listSenders = async (filters: SenderFilters = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.search) params.append('search', filters.search);
            if (filters.sort_by) params.append('sort_by', filters.sort_by);
            if (filters.sort_order) params.append('sort_order', filters.sort_order);
            if (filters.per_page) params.append('per_page', filters.per_page.toString());
            if (filters.page) params.append('page', filters.page.toString());

            const response = await axios.get<{ data: SenderListResponse }>(
                `/api/v1/senders?${params.toString()}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch senders';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Get sender details with transaction history
     */
    const getSenderDetails = async (senderId: number) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get<{ data: SenderDetailResponse }>(
                `/api/v1/senders/${senderId}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch sender details';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        listDeposits,
        getDepositStats,
        listSenders,
        getSenderDetails,
    };
}
