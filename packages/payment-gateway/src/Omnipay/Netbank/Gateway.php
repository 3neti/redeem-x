<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank;

use Omnipay\Common\AbstractGateway;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Message\{
    GenerateQrRequest,
    DisburseRequest,
    ConfirmDisbursementRequest,
    CheckBalanceRequest
};
use LBHurtado\PaymentGateway\Enums\SettlementRail;

/**
 * NetBank Gateway
 *
 * Provides access to NetBank payment gateway operations including
 * QR code generation, disbursements, and balance checking with
 * support for INSTAPAY and PESONET settlement rails.
 */
class Gateway extends AbstractGateway
{
    public function getName(): string
    {
        return 'Netbank';
    }
    
    public function getDefaultParameters(): array
    {
        return [
            'clientId' => '',
            'clientSecret' => '',
            'tokenEndpoint' => '',
            'apiUrl' => '',  // Simplified API base URL
            'apiEndpoint' => '',
            'qrEndpoint' => '',
            'statusEndpoint' => '',
            'balanceEndpoint' => '',
            'testMode' => false,
            'rails' => [],
        ];
    }
    
    // Parameter getters/setters
    
    public function getClientId(): string
    {
        return $this->getParameter('clientId');
    }
    
    public function setClientId($value)
    {
        return $this->setParameter('clientId', $value);
    }
    
    public function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }
    
    public function setClientSecret($value)
    {
        return $this->setParameter('clientSecret', $value);
    }
    
    public function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }
    
    public function setTokenEndpoint($value)
    {
        return $this->setParameter('tokenEndpoint', $value);
    }
    
    public function getApiUrl(): string
    {
        return $this->getParameter('apiUrl');
    }
    
    public function setApiUrl($value)
    {
        return $this->setParameter('apiUrl', $value);
    }
    
    public function getApiEndpoint(): string
    {
        return $this->getParameter('apiEndpoint');
    }
    
    public function setApiEndpoint($value)
    {
        return $this->setParameter('apiEndpoint', $value);
    }
    
    public function getQrEndpoint(): string
    {
        return $this->getParameter('qrEndpoint');
    }
    
    public function setQrEndpoint($value)
    {
        return $this->setParameter('qrEndpoint', $value);
    }
    
    public function getStatusEndpoint(): string
    {
        return $this->getParameter('statusEndpoint');
    }
    
    public function setStatusEndpoint($value)
    {
        return $this->setParameter('statusEndpoint', $value);
    }
    
    public function getBalanceEndpoint(): string
    {
        return $this->getParameter('balanceEndpoint');
    }
    
    public function setBalanceEndpoint($value)
    {
        return $this->setParameter('balanceEndpoint', $value);
    }
    
    public function getTestMode()
    {
        return $this->getParameter('testMode');
    }
    
    public function setTestMode($value)
    {
        return $this->setParameter('testMode', $value);
    }
    
    public function getRails(): array
    {
        return $this->getParameter('rails');
    }
    
    public function setRails($value)
    {
        return $this->setParameter('rails', $value);
    }
    
    /**
     * Check if gateway supports a specific settlement rail
     */
    public function supportsRail(SettlementRail $rail): bool
    {
        $rails = $this->getRails();
        return isset($rails[$rail->value]) && ($rails[$rail->value]['enabled'] ?? false);
    }
    
    /**
     * Get rail configuration
     */
    public function getRailConfig(SettlementRail $rail): ?array
    {
        $rails = $this->getRails();
        return $rails[$rail->value] ?? null;
    }
    
    // Custom gateway operations
    
    /**
     * Generate QR code for payment
     */
    public function generateQr(array $options = []): GenerateQrRequest
    {
        return $this->createRequest(GenerateQrRequest::class, $options);
    }
    
    /**
     * Disburse funds to a recipient
     */
    public function disburse(array $options = []): DisburseRequest
    {
        return $this->createRequest(DisburseRequest::class, $options);
    }
    
    /**
     * Confirm a disbursement operation
     */
    public function confirmDisbursement(array $options = []): ConfirmDisbursementRequest
    {
        return $this->createRequest(ConfirmDisbursementRequest::class, $options);
    }
    
    /**
     * Check account balance
     */
    public function checkBalance(array $options = []): CheckBalanceRequest
    {
        return $this->createRequest(CheckBalanceRequest::class, $options);
    }
}
