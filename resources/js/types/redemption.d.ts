/**
 * TypeScript types for voucher redemption flow
 */

export interface Bank {
    code: string;
    name: string;
}

export interface WalletFormData {
    mobile: string;
    country: string;
    bank_code?: string;
    account_number?: string;
    secret?: string;
}

export interface PluginFormData {
    [key: string]: string | number | boolean;
}

export interface RedemptionSummary {
    mobile: string;
    bank_account?: string;
    inputs: Record<string, any>;
    has_signature: boolean;
}

export interface VoucherRedemption {
    voucher_code: string;
    voucher: {
        id: number;
        code: string;
        amount: number;
        currency: string;
        expires_at?: string;
        instructions: {
            cash: {
                amount: number;
                currency: string;
            };
            inputs?: {
                fields: string[];
            };
            rider?: {
                message?: string;
                url?: string;
            };
        };
    };
}

export interface RiderInfo {
    message?: string | null;
    url?: string | null;
}
