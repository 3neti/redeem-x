<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use LBHurtado\PaymentGateway\Data\Wallet\BalanceData;
use Omnipay\Common\Message\AbstractResponse;

/**
 * NetBank Check Balance Response
 *
 * Response from NetBank balance check API.
 *
 * Example response data:
 * <code>
 * {
 *   "status": "success",
 *   "data": {
 *     "account_number": "1234567890",
 *     "balance": 1250000,
 *     "available_balance": 1200000,
 *     "currency": "PHP",
 *     "as_of": "2024-11-13T12:00:00Z"
 *   }
 * }
 * </code>
 */
class CheckBalanceResponse extends AbstractResponse
{
    /**
     * Check if the request was successful
     */
    public function isSuccessful(): bool
    {
        return isset($this->data['status']) 
            && $this->data['status'] === 'success'
            && isset($this->data['data']['balance']);
    }
    
    /**
     * Get the balance in minor units (centavos)
     */
    public function getBalance(): ?int
    {
        return $this->data['data']['balance'] ?? null;
    }
    
    /**
     * Get the available balance in minor units (centavos)
     * Available balance excludes pending transactions
     */
    public function getAvailableBalance(): ?int
    {
        return $this->data['data']['available_balance'] ?? $this->getBalance();
    }
    
    /**
     * Get the currency code
     */
    public function getCurrency(): string
    {
        return $this->data['data']['currency'] ?? 'PHP';
    }
    
    /**
     * Get the account number
     */
    public function getAccountNumber(): ?string
    {
        return $this->data['data']['account_number'] ?? null;
    }
    
    /**
     * Get the timestamp when balance was calculated
     */
    public function getAsOf(): ?string
    {
        return $this->data['data']['as_of'] ?? null;
    }
    
    /**
     * Get balance as BalanceData object
     */
    public function getBalanceData(): ?BalanceData
    {
        if (!$this->isSuccessful()) {
            return null;
        }
        
        return new BalanceData(
            amount: $this->getBalance(),
            currency: $this->getCurrency(),
        );
    }
    
    /**
     * Get available balance as BalanceData object
     */
    public function getAvailableBalanceData(): ?BalanceData
    {
        if (!$this->isSuccessful()) {
            return null;
        }
        
        return new BalanceData(
            amount: $this->getAvailableBalance(),
            currency: $this->getCurrency(),
        );
    }
    
    /**
     * Get the error message if request failed
     */
    public function getMessage(): ?string
    {
        if ($this->isSuccessful()) {
            return null;
        }
        
        return $this->data['message'] 
            ?? $this->data['error'] 
            ?? 'Unknown error occurred';
    }
    
    /**
     * Get the error code if request failed
     */
    public function getCode(): ?string
    {
        return $this->data['code'] ?? null;
    }
}
