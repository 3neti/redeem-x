import { ref } from 'vue';
import axios from '@/lib/axios';

// Types
export interface ContactData {
    id: number;
    mobile: string;
    name?: string;
    email?: string;
    country?: string;
    bank_account?: string;
    updated_at: string;
    created_at: string;
}

export interface ContactStats {
    total: number;
    withEmail: number;
    withName: number;
}

export interface ContactFilters {
    search?: string;
    per_page?: number;
    page?: number;
}

export interface ContactListResponse {
    data: ContactData[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
    filters: ContactFilters;
    stats: ContactStats;
}

export interface ContactDetailResponse {
    contact: ContactData;
}

export interface ContactVoucher {
    code: string;
    amount: number;
    currency: string;
    status: string;
    redeemed_at: string | null;
    created_at: string;
}

export interface ContactVouchersResponse {
    vouchers: ContactVoucher[];
}

export function useContactApi() {
    const loading = ref(false);
    const error = ref<string | null>(null);

    /**
     * List contacts with filters and pagination
     */
    const listContacts = async (filters: ContactFilters = {}) => {
        loading.value = true;
        error.value = null;

        try {
            const params = new URLSearchParams();
            if (filters.search) params.append('search', filters.search);
            if (filters.per_page) params.append('per_page', filters.per_page.toString());
            if (filters.page) params.append('page', filters.page.toString());

            const response = await axios.get<{ data: ContactListResponse }>(
                `/api/v1/contacts?${params.toString()}`
            );

            return response.data.data;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch contacts';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Show contact details
     */
    const showContact = async (id: number) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get<{ data: ContactDetailResponse }>(
                `/api/v1/contacts/${id}`
            );

            return response.data.data.contact;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch contact details';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    /**
     * Get contact's vouchers
     */
    const getContactVouchers = async (id: number) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get<{ data: ContactVouchersResponse }>(
                `/api/v1/contacts/${id}/vouchers`
            );

            return response.data.data.vouchers;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to fetch contact vouchers';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        loading,
        error,
        listContacts,
        showContact,
        getContactVouchers,
    };
}
