<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\PaymentGateway\Support\BankRegistry;
use Spatie\LaravelData\Data;

/**
 * Disbursement Data DTO
 * 
 * Contains disbursement transaction details extracted from voucher metadata.
 */
class DisbursementData extends Data
{
    public function __construct(
        public string $operation_id,
        public string $transaction_uuid,
        public string $status,
        public float $amount,
        public string $bank,
        public string $rail,
        public string $account,
        public string $disbursed_at,
        public ?string $bank_name = null,
        public ?string $bank_logo = null,
        public bool $is_emi = false,
    ) {}
    
    /**
     * Create from voucher metadata
     *
     * @param array|null $metadata Voucher metadata containing 'disbursement' key
     * @return static|null
     */
    public static function fromMetadata(?array $metadata): ?static
    {
        $disbursement = $metadata['disbursement'] ?? null;
        
        if (!$disbursement) {
            return null;
        }
        
        $bankRegistry = app(BankRegistry::class);
        $bankCode = $disbursement['bank'] ?? '';
        
        return new static(
            operation_id: $disbursement['operation_id'] ?? '',
            transaction_uuid: $disbursement['transaction_uuid'] ?? '',
            status: $disbursement['status'] ?? 'Unknown',
            amount: (float) ($disbursement['amount'] ?? 0),
            bank: $bankCode,
            rail: $disbursement['rail'] ?? '',
            account: $disbursement['account'] ?? '',
            disbursed_at: $disbursement['disbursed_at'] ?? '',
            bank_name: $bankRegistry->getBankName($bankCode),
            bank_logo: $bankRegistry->getBankLogo($bankCode),
            is_emi: $bankRegistry->isEMI($bankCode),
        );
    }
    
    /**
     * Get masked account number
     * Shows only last 4 digits: 09173011987 → ***1987
     *
     * @return string
     */
    public function getMaskedAccount(): string
    {
        if (strlen($this->account) <= 4) {
            return $this->account;
        }
        
        return '***' . substr($this->account, -4);
    }
}
