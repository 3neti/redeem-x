<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\MoneyIssuer\Support\BankRegistry;
use LBHurtado\PaymentGateway\Enums\DisbursementStatus;
use Spatie\LaravelData\Data;

/**
 * Disbursement Data DTO
 * 
 * Generic DTO for disbursement transactions that supports multiple payment gateways.
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
        
        if (!$disbursement || !isset($disbursement['gateway'])) {
            return null;
        }
        
        return new static(
            gateway: $disbursement['gateway'],
            transaction_id: $disbursement['transaction_id'],
            status: $disbursement['status'] ?? 'Unknown',
            amount: (float) ($disbursement['amount'] ?? 0),
            currency: $disbursement['currency'] ?? 'PHP',
            recipient_identifier: $disbursement['recipient_identifier'],
            disbursed_at: $disbursement['disbursed_at'],
            transaction_uuid: $disbursement['transaction_uuid'] ?? null,
            recipient_name: $disbursement['recipient_name'] ?? null,
            payment_method: $disbursement['payment_method'] ?? null,
            metadata: $disbursement['metadata'] ?? null,
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
        if (strlen($this->recipient_identifier) <= 4) {
            return $this->recipient_identifier;
        }
        
        return '***' . substr($this->recipient_identifier, -4);
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
    
    /**
     * Get status as DisbursementStatus enum
     *
     * @return DisbursementStatus
     */
    public function getStatusEnum(): DisbursementStatus
    {
        return DisbursementStatus::fromGateway($this->gateway, $this->status);
    }
    
    /**
     * Check if disbursement is in a final state (no more updates expected)
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return $this->getStatusEnum()->isFinal();
    }
    
    /**
     * Check if disbursement is pending or processing
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->getStatusEnum()->isPending();
    }
    
    /**
     * Get badge variant for UI display
     *
     * @return string
     */
    public function getStatusBadgeVariant(): string
    {
        return $this->getStatusEnum()->getBadgeVariant();
    }
    
    /**
     * Get status display label
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        return $this->getStatusEnum()->getLabel();
    }
}
