import type { BulkVoucherItem, VoucherData } from './useVoucherApi';

export function useCsvVouchers() {
    /**
     * Parse CSV file to bulk voucher items
     * Expected columns: mobile, external_id, external_type, reference_id, user_id, custom_json
     */
    const parseCsv = (csvText: string): BulkVoucherItem[] => {
        const lines = csvText.trim().split('\n');
        if (lines.length === 0) return [];

        // Parse header
        const headers = lines[0].split(',').map((h) => h.trim().toLowerCase());
        
        // Map column indexes
        const mobileIdx = headers.indexOf('mobile');
        const externalIdIdx = headers.indexOf('external_id');
        const externalTypeIdx = headers.indexOf('external_type');
        const referenceIdIdx = headers.indexOf('reference_id');
        const userIdIdx = headers.indexOf('user_id');
        const customIdx = headers.indexOf('custom_json');

        const vouchers: BulkVoucherItem[] = [];

        // Parse data rows (skip header)
        for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;

            const values = line.split(',').map((v) => v.trim());
            
            const voucher: BulkVoucherItem = {};

            // Mobile
            if (mobileIdx >= 0 && values[mobileIdx]) {
                voucher.mobile = values[mobileIdx];
            }

            // External metadata
            const hasExternalData =
                (externalIdIdx >= 0 && values[externalIdIdx]) ||
                (externalTypeIdx >= 0 && values[externalTypeIdx]) ||
                (referenceIdIdx >= 0 && values[referenceIdIdx]) ||
                (userIdIdx >= 0 && values[userIdIdx]) ||
                (customIdx >= 0 && values[customIdx]);

            if (hasExternalData) {
                voucher.external_metadata = {};

                if (externalIdIdx >= 0 && values[externalIdIdx]) {
                    voucher.external_metadata.external_id = values[externalIdIdx];
                }
                if (externalTypeIdx >= 0 && values[externalTypeIdx]) {
                    voucher.external_metadata.external_type = values[externalTypeIdx];
                }
                if (referenceIdIdx >= 0 && values[referenceIdIdx]) {
                    voucher.external_metadata.reference_id = values[referenceIdIdx];
                }
                if (userIdIdx >= 0 && values[userIdIdx]) {
                    voucher.external_metadata.user_id = values[userIdIdx];
                }
                if (customIdx >= 0 && values[customIdx]) {
                    try {
                        voucher.external_metadata.custom = JSON.parse(values[customIdx]);
                    } catch (e) {
                        console.error('Failed to parse custom JSON at line', i, ':', e);
                    }
                }
            }

            vouchers.push(voucher);
        }

        return vouchers;
    };

    /**
     * Export vouchers to CSV
     */
    const exportToCsv = (vouchers: VoucherData[]): string => {
        const headers = [
            'code',
            'amount',
            'currency',
            'status',
            'created_at',
            'external_id',
            'external_type',
            'user_id',
        ];

        const rows = vouchers.map((v) => {
            return [
                v.code,
                v.amount,
                v.currency,
                v.status,
                v.created_at,
                (v as any).external_metadata?.external_id || '',
                (v as any).external_metadata?.external_type || '',
                (v as any).external_metadata?.user_id || '',
            ].join(',');
        });

        return [headers.join(','), ...rows].join('\n');
    };

    /**
     * Download CSV file
     */
    const downloadCsv = (content: string, filename: string) => {
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        URL.revokeObjectURL(url);
    };

    /**
     * Get sample CSV template
     */
    const getSampleCsv = (): string => {
        return `mobile,external_id,external_type,reference_id,user_id,custom_json
09171234567,quest-001,questpay,ref-001,player-abc,"{""level"":10,""mission"":""delivery""}"
09179876543,quest-002,questpay,ref-002,player-xyz,"{""level"":15,""mission"":""survey""}"
09181112233,quest-003,questpay,ref-003,player-def,"{""level"":20,""mission"":""pickup""}"`;
    };

    return {
        parseCsv,
        exportToCsv,
        downloadCsv,
        getSampleCsv,
    };
}
