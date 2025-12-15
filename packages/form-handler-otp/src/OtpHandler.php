<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerOtp\Actions\GenerateOtp;
use LBHurtado\FormHandlerOtp\Actions\ValidateOtp;
use LBHurtado\FormHandlerOtp\Data\OtpData;
use LBHurtado\FormHandlerOtp\Services\SmsService;

/**
 * OTP Handler
 * 
 * Handles OTP generation, SMS delivery, and validation for form flows.
 */
class OtpHandler implements FormHandlerInterface
{
    public function getName(): string
    {
        return 'otp';
    }
    
    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        // Extract data from 'data' key if present (from form submission)
        $inputData = $request->input('data', $request->all());
        
        // Get reference ID and mobile from context
        $referenceId = $context['flow_id'] ?? $context['reference_id'] ?? 'unknown';
        $mobile = $context['mobile'] ?? $inputData['mobile'] ?? '';
        
        // Check if this is a resend request
        if ($request->input('resend')) {
            return $this->handleResend($referenceId, $mobile);
        }
        
        // Validate submitted OTP
        $validated = validator($inputData, [
            'otp_code' => 'required|string|min:4|max:6',
        ])->validate();
        
        // Validate OTP against cached value
        $validator = new ValidateOtp(
            cachePrefix: config('otp-handler.cache_prefix'),
            period: config('otp-handler.period'),
            digits: config('otp-handler.digits'),
        );
        
        $isValid = $validator->execute($referenceId, $validated['otp_code']);
        
        if (!$isValid) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'otp_code' => ['The OTP code is invalid or has expired.'],
            ]);
        }
        
        // Return validated data
        return OtpData::from([
            'mobile' => $mobile,
            'otp_code' => $validated['otp_code'],
            'verified_at' => now()->toIso8601String(),
            'reference_id' => $referenceId,
        ])->toArray();
    }
    
    public function validate(array $data, array $rules): bool
    {
        // Validation handled in handle() method
        return true;
    }
    
    public function render(FormFlowStepData $step, array $context = [])
    {
        $referenceId = $context['flow_id'] ?? $context['reference_id'] ?? 'unknown';
        
        // Get mobile from context or from collected data
        $mobile = $context['mobile'] ?? '';
        
        // If not in context, try to get from session collected data (from wallet_info step)
        if (empty($mobile) && isset($context['flow_id'])) {
            $flowState = Session::get("form_flow.{$context['flow_id']}");
            $mobile = $flowState['collected_data']['wallet_info']['mobile'] ?? 
                      $flowState['collected_data']['mobile'] ?? '';
        }
        
        // Generate OTP on first visit (if not already generated)
        $sessionKey = "otp_sent.{$referenceId}";
        
        if (!Session::has($sessionKey)) {
            $generator = new GenerateOtp(
                cachePrefix: config('otp-handler.cache_prefix'),
                period: config('otp-handler.period'),
                digits: config('otp-handler.digits'),
            );
            
            $result = $generator->execute($referenceId, $mobile);
            
            // Send SMS via SmsService
            $smsService = new SmsService(
                provider: config('otp-handler.sms_provider', 'engagespark'),
                senderId: config('otp-handler.engagespark.sender_id')
            );
            
            $smsService->sendOtp($mobile, $result['code'], config('otp-handler.label'));
            
            // Mark as sent
            Session::put($sessionKey, now()->timestamp);
        }
        
        // Render OTP capture page
        return Inertia::render('form-flow/otp/OtpCapturePage', [
            'flow_id' => $context['flow_id'] ?? null,
            'step' => (string) ($context['step_index'] ?? 0),
            'mobile' => $mobile,
            'config' => array_merge([
                'max_resends' => config('otp-handler.max_resends', 3),
                'resend_cooldown' => config('otp-handler.resend_cooldown', 30),
                'digits' => config('otp-handler.digits', 4),
            ], $step->config),
        ]);
    }
    
    public function getConfigSchema(): array
    {
        return [
            'max_resends' => 'integer|min:1|max:10',
            'resend_cooldown' => 'integer|min:10|max:120',
            'digits' => 'integer|in:4,5,6',
        ];
    }
    
    /**
     * Handle OTP resend request
     */
    protected function handleResend(string $referenceId, string $mobile): array
    {
        $generator = new GenerateOtp(
            cachePrefix: config('otp-handler.cache_prefix'),
            period: config('otp-handler.period'),
            digits: config('otp-handler.digits'),
        );
        
        $result = $generator->execute($referenceId, $mobile);
        
        // Send SMS via SmsService
        $smsService = new SmsService(
            provider: config('otp-handler.sms_provider', 'engagespark'),
            senderId: config('otp-handler.engagespark.sender_id')
        );
        
        $smsService->sendOtp($mobile, $result['code'], config('otp-handler.label'));
        
        return ['resent' => true];
    }
}
