<?php

namespace LBHurtado\PaymentGateway\Gateways\Omnipay;

use LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface;
use LBHurtado\PaymentGateway\Omnipay\Support\OmnipayFactory;
use LBHurtado\PaymentGateway\Data\Netbank\Disburse\{
    DisburseInputData,
    DisburseResponseData
};
use LBHurtado\PaymentGateway\Support\BankRegistry;
use LBHurtado\PaymentGateway\Enums\SettlementRail;
use Omnipay\Common\GatewayInterface;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Models\Transaction;
use Brick\Money\Money;
use Illuminate\Support\Facades\{DB, Log};
use LBHurtado\Wallet\Events\DisbursementConfirmed;

/**
 * Omnipay-based payment gateway adapter.
 * 
 * Implements PaymentGatewayInterface using the Omnipay framework,
 * providing settlement rail validation, EMI detection, and KYC workarounds.
 */
class OmnipayPaymentGateway implements PaymentGatewayInterface
{
    protected GatewayInterface $gateway;
    protected BankRegistry $bankRegistry;
    
    public function __construct()
    {
        $gatewayName = config('payment-gateway.default', 'netbank');
        $this->gateway = OmnipayFactory::create($gatewayName);
        $this->bankRegistry = app(BankRegistry::class);
    }
    
    /**
     * Disburse funds to a recipient via Omnipay.
     * 
     * @param Wallet $wallet The wallet to debit
     * @param DisburseInputData|array $validated Disbursement data
     * @return DisburseResponseData|bool Response data or false on failure
     */
    public function disburse(
        Wallet $wallet, 
        DisburseInputData|array $validated
    ): DisburseResponseData|bool {
        // Convert to array if DisburseInputData
        $data = $validated instanceof DisburseInputData
            ? $validated->toArray()
            : $validated;
        
        Log::debug('[OmnipayPaymentGateway] Starting disbursement', [
            'wallet_id' => $wallet->getKey(),
            'reference' => $data['reference'],
            'amount' => $data['amount'],
            'bank' => $data['bank'],
            'via' => $data['via'],
        ]);
        
        $amount = $data['amount'];
        $currency = config('disbursement.currency', 'PHP');
        $credits = Money::of($amount, $currency);
        
        // Validate settlement rail
        try {
            $rail = SettlementRail::from($data['via']);
            $this->validateBankSupportsRail($data['bank'], $rail);
        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Settlement rail validation failed', [
                'error' => $e->getMessage(),
                'bank' => $data['bank'],
                'via' => $data['via'],
            ]);
            return false;
        }
        
        DB::beginTransaction();
        
        try {
            // Reserve funds (not confirmed yet)
            $transaction = $wallet->withdraw(
                $credits->getMinorAmount()->toInt(),
                [],
                false // not confirmed
            );
            
            Log::debug('[OmnipayPaymentGateway] Funds reserved', [
                'transaction_uuid' => $transaction->uuid,
                'amount_centavos' => $credits->getMinorAmount()->toInt(),
            ]);
            
            // Convert amount to minor units (centavos)
            $amountInCentavos = (int) ($amount * 100);
            
            // Call Omnipay gateway
            $response = $this->gateway->disburse([
                'amount' => $amountInCentavos,
                'accountNumber' => $data['account_number'],
                'bankCode' => $data['bank'],
                'reference' => $data['reference'],
                'via' => $data['via'],
                'currency' => $currency,
            ])->send();
            
            if (!$response->isSuccessful()) {
                Log::warning('[OmnipayPaymentGateway] Disbursement failed', [
                    'message' => $response->getMessage(),
                    'code' => $response->getCode(),
                    'rail' => $rail->value,
                    'bank' => $data['bank'],
                    'reference' => $data['reference'],
                ]);
                DB::rollBack();
                return false;
            }
            
            // Store operation ID and rail info in transaction meta
            $transaction->meta = [
                'operationId' => $response->getTransactionId(),
                'user_id' => $wallet->getKey(),
                'payload' => $data,
                'settlement_rail' => $rail->value,
                'bank_code' => $data['bank'],
                'is_emi' => $this->bankRegistry->isEMI($data['bank']),
            ];
            $transaction->save();
            
            DB::commit();
            
            Log::info('[OmnipayPaymentGateway] Disbursement initiated', [
                'transaction_uuid' => $transaction->uuid,
                'operation_id' => $response->getTransactionId(),
                'rail' => $rail->value,
                'bank' => $data['bank'],
                'amount' => $amount,
                'status' => $response->getStatus(),
            ]);
            
            // Return response DTO
            return DisburseResponseData::from([
                'uuid' => $transaction->uuid,
                'transaction_id' => $response->getTransactionId(),
                'status' => $response->getStatus(),
            ]);
            
        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Disbursement error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'rail' => $rail->value ?? null,
                'bank' => $data['bank'] ?? null,
            ]);
            DB::rollBack();
            return false;
        }
    }
    
    /**
     * Generate QR code for payment collection.
     * 
     * @param string $account Account number
     * @param Money $amount Amount to collect
     * @return string QR code data (base64 PNG)
     */
    public function generate(string $account, Money $amount): string
    {
        Log::debug('[OmnipayPaymentGateway] Generating QR code', [
            'account' => $account,
            'amount' => $amount->getAmount()->toFloat(),
        ]);
        
        // Convert to minor units
        $amountInCentavos = $amount->getMinorAmount()->toInt();
        
        $response = $this->gateway->generateQr([
            'accountNumber' => $account,
            'amount' => $amountInCentavos,
            'currency' => $amount->getCurrency()->getCurrencyCode(),
        ])->send();
        
        if (!$response->isSuccessful()) {
            Log::error('[OmnipayPaymentGateway] QR generation failed', [
                'message' => $response->getMessage(),
            ]);
            throw new \RuntimeException('Failed to generate QR code: ' . $response->getMessage());
        }
        
        Log::info('[OmnipayPaymentGateway] QR code generated successfully');
        
        return $response->getQrCode();
    }
    
    /**
     * Confirm a deposit transaction.
     * 
     * @param array $payload Webhook payload
     * @return bool Success status
     */
    public function confirmDeposit(array $payload): bool
    {
        Log::info('[OmnipayPaymentGateway] Deposit confirmation received', [
            'payload' => $payload,
        ]);
        
        // TODO: Implement deposit confirmation logic based on webhook payload
        // This would typically:
        // 1. Validate webhook signature
        // 2. Find associated transaction
        // 3. Confirm the transaction
        // 4. Fire events
        
        return true;
    }
    
    /**
     * Confirm a disbursement operation.
     * 
     * @param string $operationId Gateway operation ID
     * @return bool Success status
     */
    public function confirmDisbursement(string $operationId): bool
    {
        try {
            $transaction = Transaction::whereJsonContains('meta->operationId', $operationId)
                ->firstOrFail();
            
            Log::debug('[OmnipayPaymentGateway] Found transaction for confirmation', [
                'operation_id' => $operationId,
                'transaction_uuid' => $transaction->uuid,
            ]);
            
            // Confirm the transaction
            $transaction->payable->confirm($transaction);
            
            // Dispatch event
            DisbursementConfirmed::dispatch($transaction);
            
            $rail = $transaction->meta['settlement_rail'] ?? 'unknown';
            $bank = $transaction->meta['bank_code'] ?? 'unknown';
            
            Log::info('[OmnipayPaymentGateway] Disbursement confirmed', [
                'operation_id' => $operationId,
                'transaction_uuid' => $transaction->uuid,
                'rail' => $rail,
                'bank' => $bank,
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            Log::error('[OmnipayPaymentGateway] Confirm disbursement failed', [
                'operation_id' => $operationId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Validate that bank supports the selected settlement rail.
     * 
     * @param string $bankCode SWIFT BIC code
     * @param SettlementRail $rail Settlement rail
     * @throws \InvalidArgumentException If bank doesn't support rail
     */
    protected function validateBankSupportsRail(string $bankCode, SettlementRail $rail): void
    {
        if (!$this->bankRegistry->supportsRail($bankCode, $rail)) {
            throw new \InvalidArgumentException(
                "Bank {$bankCode} does not support {$rail->value} settlement rail"
            );
        }
    }
}
