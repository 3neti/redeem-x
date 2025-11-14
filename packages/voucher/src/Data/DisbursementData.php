<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\PaymentGateway\Support\BankRegistry;
use Spatie\LaravelData\Data;

/**
 * Disbursement Data DTO
 * 
 * Generic DTO for disbursement transactions that supports multiple payment gateways.
 * Maintains backward compatibility with legacy NetBank format.
 * 
 * Core fields are gateway-agnostic, while gateway-specific data is stored in metadata.
 */
class DisbursementData extends Data
{
    public function __construct(
        // Core fields (gateway-agnostic)
        public string $gateway,                  // 'netbank', 'icash', 'paypal', 'stripe', 'gcash', etc.
        public string $transaction_id,           // Gateway's transaction reference
        public string $status,                   // 'pending', 'completed', 'failed'
        public float $amount,                    // Amount disbursed
        public string $currency,                 // 'PHP', 'USD', etc.
        public string $recipient_identifier,     // Account number, email, mobile, etc.
        public string $disbursed_at,             // ISO 8601 timestamp
        public ?string $transaction_uuid = null, // Internal transaction UUID
        public ?string $recipient_name = null,   // Display name (e.g., "GCash", "john@example.com")
        public ?string $payment_method = null,   // 'bank_transfer', 'e_wallet', 'card', etc.
        public ?array $metadata = null,          // Gateway-specific extra data
        
        // Legacy fields (deprecated, kept for backward compatibility)
        /** @deprecated Use transaction_id instead */
        public ?string $operation_id = null,
        /** @deprecated Use metadata.bank_code instead */
        public ?string $bank = null,
        /** @deprecated Use metadata.rail instead */
        public ?string $rail = null,
        /** @deprecated Use recipient_identifier instead */
        public ?string $account = null,
        /** @deprecated Use metadata.bank_name or recipient_name instead */
        public ?string $bank_name = null,
        /** @deprecated Use metadata.bank_logo instead */
        public ?string $bank_logo = null,
        /** @deprecated Use metadata.is_emi instead */
        public bool $is_emi = false,
    ) {}
    
    /**
     * Create from voucher metadata
     * 
     * Supports both new generic format and legacy NetBank format.
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
        
        // Try new generic format first
        if (isset($disbursement['gateway'])) {
            return static::fromGenericFormat($disbursement);
        }
        
        // Fall back to legacy NetBank format
        return static::fromLegacyNetbankFormat($disbursement);
    }
    
    /**
     * Create from new generic format
     *
     * @param array $data Disbursement data in generic format
     * @return static
     */
    protected static function fromGenericFormat(array $data): static
    {
        // Extract legacy fields for backward compatibility
        $legacyOperationId = $data['metadata']['operation_id'] ?? $data['transaction_id'];
        $legacyBank = $data['metadata']['bank_code'] ?? null;
        $legacyRail = $data['metadata']['rail'] ?? null;
        $legacyAccount = $data['metadata']['account'] ?? $data['recipient_identifier'];
        $legacyBankName = $data['metadata']['bank_name'] ?? $data['recipient_name'];
        $legacyBankLogo = $data['metadata']['bank_logo'] ?? null;
        $legacyIsEmi = $data['metadata']['is_emi'] ?? false;
        
        return new static(
            gateway: $data['gateway'],
            transaction_id: $data['transaction_id'],
            status: $data['status'] ?? 'Unknown',
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'PHP',
            recipient_identifier: $data['recipient_identifier'],
            disbursed_at: $data['disbursed_at'],
            transaction_uuid: $data['transaction_uuid'] ?? null,
            recipient_name: $data['recipient_name'] ?? null,
            payment_method: $data['payment_method'] ?? null,
            metadata: $data['metadata'] ?? null,
            
            // Populate legacy fields for backward compatibility
            operation_id: $legacyOperationId,
            bank: $legacyBank,
            rail: $legacyRail,
            account: $legacyAccount,
            bank_name: $legacyBankName,
            bank_logo: $legacyBankLogo,
            is_emi: $legacyIsEmi,
        );
    }
    
    /**
     * Create from legacy NetBank format
     * 
     * Maps old NetBank-specific fields to new generic structure.
     *
     * @param array $data Disbursement data in legacy NetBank format
     * @return static
     */
    protected static function fromLegacyNetbankFormat(array $data): static
    {
        $bankRegistry = app(BankRegistry::class);
        $bankCode = $data['bank'] ?? '';
        $operationId = $data['operation_id'] ?? '';
        $account = $data['account'] ?? '';
        $rail = $data['rail'] ?? '';
        $bankName = $bankRegistry->getBankName($bankCode);
        $bankLogo = $bankRegistry->getBankLogo($bankCode);
        $isEmi = $bankRegistry->isEMI($bankCode);
        
        return new static(
            // Map to new generic format
            gateway: 'netbank',
            transaction_id: $operationId,
            status: $data['status'] ?? 'Unknown',
            amount: (float) ($data['amount'] ?? 0),
            currency: 'PHP',
            recipient_identifier: $account,
            disbursed_at: $data['disbursed_at'] ?? '',
            transaction_uuid: $data['transaction_uuid'] ?? null,
            recipient_name: $bankName,
            payment_method: 'bank_transfer',
            metadata: [
                'bank_code' => $bankCode,
                'bank_name' => $bankName,
                'bank_logo' => $bankLogo,
                'rail' => $rail,
                'is_emi' => $isEmi,
            ],
            
            // Keep legacy fields populated
            operation_id: $operationId,
            bank: $bankCode,
            rail: $rail,
            account: $account,
            bank_name: $bankName,
            bank_logo: $bankLogo,
            is_emi: $isEmi,
        );
    }
    
    /**
     * Get masked account/identifier
     * Shows only last 4 characters: 09173011987 → ***1987
     *
     * @return string
     */
    public function getMaskedAccount(): string
    {
        // Use new field, fall back to legacy
        $identifier = $this->recipient_identifier ?? $this->account ?? '';
        
        if (strlen($identifier) <= 4) {
            return $identifier;
        }
        
        return '***' . substr($identifier, -4);
    }
    
    /**
     * Get masked identifier (alias for getMaskedAccount)
     *
     * @return string
     */
    public function getMaskedIdentifier(): string
    {
        return $this->getMaskedAccount();
    }
    
    /**
     * Get gateway icon path
     *
     * @return string|null
     */
    public function getGatewayIcon(): ?string
    {
        return match($this->gateway) {
            'netbank', 'icash' => '/images/gateways/ph-banking.svg',
            'paypal' => '/images/gateways/paypal.svg',
            'stripe' => '/images/gateways/stripe.svg',
            'gcash' => '/images/gateways/gcash.svg',
            default => null,
        };
    }
    
    /**
     * Get payment method display name
     *
     * @return string
     */
    public function getPaymentMethodDisplay(): string
    {
        return match($this->payment_method) {
            'bank_transfer' => 'Bank Transfer',
            'e_wallet' => 'E-Wallet',
            'card' => 'Credit/Debit Card',
            default => $this->payment_method ?? 'Unknown',
        };
    }
    
    // Gateway-specific helper methods
    
    /**
     * Get bank code (NetBank/ICash specific)
     *
     * @return string|null
     */
    public function getBankCode(): ?string
    {
        return $this->metadata['bank_code'] ?? $this->bank;
    }
    
    /**
     * Get settlement rail (NetBank/ICash specific)
     *
     * @return string|null
     */
    public function getRail(): ?string
    {
        return $this->metadata['rail'] ?? $this->rail;
    }
    
    /**
     * Get bank name
     *
     * @return string|null
     */
    public function getBankName(): ?string
    {
        return $this->metadata['bank_name'] ?? $this->bank_name ?? $this->recipient_name;
    }
    
    /**
     * Get bank logo path
     *
     * @return string|null
     */
    public function getBankLogo(): ?string
    {
        return $this->metadata['bank_logo'] ?? $this->bank_logo;
    }
    
    /**
     * Check if recipient is an EMI (e-Money Issuer) like GCash or PayMaya
     *
     * @return bool
     */
    public function isEMI(): bool
    {
        return $this->metadata['is_emi'] ?? $this->is_emi;
    }
}
