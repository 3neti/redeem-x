import { ref } from 'vue';
import axios from 'axios';

export interface DashboardStats {
    vouchers: {
        total: number;
        active: number;
        redeemed: number;
        expired: number;
    };
    transactions: {
        today: number;
        this_month: number;
        total_amount: number;
        currency: string;
    };
    deposits: {
        today: number;
        this_month: number;
        total_amount: number;
        unique_senders: number;
        currency: string;
    };
    wallet: {
        balance: number;
        currency: string;
    };
    billing: {
        current_month_charges: number;
        total_vouchers_generated: number;
        currency: string;
    };
    disbursements: {
        success_rate: number;
        total_attempts: number;
        successful: number;
        failed: number;
    };
}

export interface RecentActivity {
    generations: Array<{
        id: number;
        type: string;
        campaign_name: string;
        voucher_count: number;
        total_amount: number;
        total_charge: number;
        currency: string;
        generated_at: string;
    }>;
    redemptions: Array<{
        id: number;
        type: string;
        code: string;
        amount: number;
        currency: string;
        mobile: string;
        status: string;
        redeemed_at: string;
    }>;
    deposits: Array<{
        id: number;
        type: string;
        amount: number;
        currency: string;
        gateway: string;
        created_at: string;
    }>;
    topups: Array<{
        id: number;
        type: string;
        amount: number;
        currency: string;
        gateway: string;
        institution: string;
        paid_at: string;
    }>;
}

export function useDashboardApi() {
    const loading = ref(false);
    const error = ref<Error | null>(null);

    const getStats = async (): Promise<DashboardStats | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get('/api/v1/dashboard/stats');
            return response.data.data.stats;
        } catch (err) {
            error.value =
                err instanceof Error ? err : new Error('Failed to fetch stats');
            return null;
        } finally {
            loading.value = false;
        }
    };

    const getActivity = async (): Promise<RecentActivity | null> => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get('/api/v1/dashboard/activity');
            return response.data.data.activity;
        } catch (err) {
            error.value =
                err instanceof Error
                    ? err
                    : new Error('Failed to fetch activity');
            return null;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        getStats,
        getActivity,
    };
}
