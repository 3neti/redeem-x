<?php

namespace LBHurtado\PaymentGateway\Omnipay\Netbank\Message;

use Omnipay\Common\Message\AbstractRequest;
use LBHurtado\PaymentGateway\Omnipay\Netbank\Traits\{
    HasOAuth2,
    ValidatesSettlementRail,
    AppliesKycWorkaround
};
use LBHurtado\PaymentGateway\Enums\SettlementRail;

/**
 * NetBank Disburse Request
 *
 * Handles disbursement requests to NetBank with settlement rail validation
 * and KYC workarounds.
 */
class DisburseRequest extends AbstractRequest
{
    use HasOAuth2;
    use ValidatesSettlementRail;
    use AppliesKycWorkaround;
    
    public function getData(): array
    {
        // Validate required parameters
        $this->validate(
            'amount',
            'accountNumber',
            'bankCode',
            'reference',
            'via'
        );
        
        // Parse rail enum
        $rail = SettlementRail::from($this->getVia());
        
        // Validate settlement rail
        $this->validateSettlementRail(
            $this->getBankCode(),
            $rail,
            $this->getAmount()
        );
        
        // Build NetBank API payload matching x-change package structure
        $payload = [
            'reference_id' => $this->getReference(),
            'amount' => [
                'cur' => $this->getCurrency() ?? 'PHP',
                'num' => (string) $this->getAmount(), // MUST be string, not integer
            ],
            'settlement_rail' => $rail->value,
            'source_account_number' => $this->getSourceAccountNumber() ?? config('omnipay.gateways.netbank.options.sourceAccountNumber'),
            'destination_account' => [
                'bank_code' => $this->getBankCode(),
                'account_number' => $this->getAccountNumber(),
            ],
            'recipient' => [
                'name' => $this->getAccountNumber(), // Use account as name for simplicity
            ],
            'sender' => [
                'name' => config('app.name', 'System'),
                'customer_id' => $this->getSenderCustomerId() ?? config('omnipay.gateways.netbank.options.senderCustomerId'),
            ],
        ];
        
        // Apply KYC workaround (inject random address to both sender and recipient)
        // NetBank requires address for both parties
        $this->applyKycWorkaround($payload, 'sender');
        $this->applyKycWorkaround($payload, 'recipient');
        
        return $payload;
    }
    
    public function sendData($data): DisburseResponse
    {
        try {
            // Get OAuth token
            $token = $this->getAccessToken();
            
            // Make HTTP request
            $httpResponse = $this->httpClient->request(
                'POST',
                $this->getEndpoint(),
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                json_encode($data)
            );
            
            // Parse response
            $body = $httpResponse->getBody()->getContents();
            $responseData = json_decode($body, true);
            
            return $this->response = new DisburseResponse($this, $responseData);
            
        } catch (\Exception $e) {
            return $this->response = new DisburseResponse($this, [
                'success' => false,
                'message' => $e->getMessage(),
                'error' => true,
            ]);
        }
    }
    
    protected function getEndpoint(): string
    {
        return $this->getApiEndpoint();
    }
    
    // Parameter getters/setters
    
    public function getAmount()
    {
        return $this->getParameter('amount');
    }
    
    public function setAmount($value)
    {
        return $this->setParameter('amount', $value);
    }
    
    public function getAccountNumber()
    {
        return $this->getParameter('accountNumber');
    }
    
    public function setAccountNumber($value)
    {
        return $this->setParameter('accountNumber', $value);
    }
    
    public function getBankCode()
    {
        return $this->getParameter('bankCode');
    }
    
    public function setBankCode($value)
    {
        return $this->setParameter('bankCode', $value);
    }
    
    public function getReference()
    {
        return $this->getParameter('reference');
    }
    
    public function setReference($value)
    {
        return $this->setParameter('reference', $value);
    }
    
    public function getVia()
    {
        return $this->getParameter('via');
    }
    
    public function setVia($value)
    {
        return $this->setParameter('via', $value);
    }
    
    public function getCurrency()
    {
        return $this->getParameter('currency');
    }
    
    public function setCurrency($value)
    {
        return $this->setParameter('currency', $value);
    }

    // Additional parameters from gateway/env
    public function getSourceAccountNumber()
    {
        return $this->getParameter('sourceAccountNumber');
    }

    public function setSourceAccountNumber($value)
    {
        return $this->setParameter('sourceAccountNumber', $value);
    }

    public function getSenderCustomerId()
    {
        return $this->getParameter('senderCustomerId');
    }

    public function setSenderCustomerId($value)
    {
        return $this->setParameter('senderCustomerId', $value);
    }

    public function getRecipientName()
    {
        return $this->getParameter('recipientName');
    }

    public function setRecipientName($value)
    {
        return $this->setParameter('recipientName', $value);
    }

    public function getSenderName()
    {
        return $this->getParameter('senderName');
    }

    public function setSenderName($value)
    {
        return $this->setParameter('senderName', $value);
    }
    
    // Gateway parameter access (for traits)
    
    protected function getApiEndpoint(): string
    {
        return $this->getParameter('apiEndpoint');
    }
    
    public function setApiEndpoint($value)
    {
        return $this->setParameter('apiEndpoint', $value);
    }
    
    protected function getClientId(): string
    {
        return $this->getParameter('clientId');
    }
    
    public function setClientId($value)
    {
        return $this->setParameter('clientId', $value);
    }
    
    protected function getClientSecret(): string
    {
        return $this->getParameter('clientSecret');
    }
    
    public function setClientSecret($value)
    {
        return $this->setParameter('clientSecret', $value);
    }
    
    protected function getTokenEndpoint(): string
    {
        return $this->getParameter('tokenEndpoint');
    }
    
    public function setTokenEndpoint($value)
    {
        return $this->setParameter('tokenEndpoint', $value);
    }
    
    public function getRails()
    {
        return $this->getParameter('rails');
    }
    
    public function setRails($value)
    {
        return $this->setParameter('rails', $value);
    }
}
