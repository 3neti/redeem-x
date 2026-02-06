<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Number;

/**
 * Exception thrown when a user has insufficient wallet balance
 * to generate vouchers (including all fees).
 */
class InsufficientFundsException extends Exception
{
    public function __construct(
        public readonly float $required,
        public readonly float $available,
        public readonly array $breakdown = [],
        public readonly string $currency = 'PHP'
    ) {
        $formattedRequired = Number::format($required, locale: 'en_PH');
        $formattedAvailable = Number::format($available, locale: 'en_PH');
        
        parent::__construct(
            "Insufficient funds. Required: ₱{$formattedRequired}, Available: ₱{$formattedAvailable}"
        );
    }
    
    /**
     * Get a formatted message suitable for SMS responses.
     */
    public function toSmsMessage(): string
    {
        return $this->getMessage();
    }
    
    /**
     * Get a formatted message suitable for API responses.
     */
    public function toApiMessage(): string
    {
        return 'Insufficient wallet balance to generate vouchers.';
    }
    
    /**
     * Get detailed breakdown for debugging/logging.
     */
    public function getDetails(): array
    {
        return [
            'required' => $this->required,
            'available' => $this->available,
            'shortfall' => $this->required - $this->available,
            'currency' => $this->currency,
            'breakdown' => $this->breakdown,
        ];
    }
}
