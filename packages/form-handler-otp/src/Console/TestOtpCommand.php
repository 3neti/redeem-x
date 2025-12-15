<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerOtp\Console;

use Illuminate\Console\Command;
use LBHurtado\FormHandlerOtp\Actions\GenerateOtp;
use LBHurtado\FormHandlerOtp\Actions\ValidateOtp;
use LBHurtado\FormHandlerOtp\Services\SmsService;

class TestOtpCommand extends Command
{
    protected $signature = 'test:otp 
                            {mobile=09173011987 : Mobile number to send OTP to}
                            {--no-sms : Skip sending SMS (just generate OTP)}
                            {--validate= : Validate a submitted OTP code}';

    protected $description = 'Test OTP generation, SMS sending, and validation';

    public function handle(): int
    {
        $mobile = $this->argument('mobile');
        $validateCode = $this->option('validate');
        $skipSms = $this->option('no-sms');
        
        $referenceId = 'test-' . now()->format('YmdHis');
        
        $this->info("📱 Testing OTP Handler");
        $this->newLine();
        
        // Display configuration
        $this->line("Configuration:");
        $this->line("  Mobile: {$mobile}");
        $this->line("  Digits: " . config('otp-handler.digits', 4));
        $this->line("  Period: " . config('otp-handler.period', 600) . " seconds");
        $this->line("  Provider: " . config('otp-handler.sms_provider', 'engagespark'));
        $this->line("  Sender ID: " . config('otp-handler.engagespark.sender_id', 'cashless'));
        $this->newLine();
        
        // If validating
        if ($validateCode) {
            return $this->validateOtp($referenceId, $validateCode);
        }
        
        // Generate OTP
        $this->info("🔐 Generating OTP...");
        
        try {
            $generator = new GenerateOtp(
                cachePrefix: config('otp-handler.cache_prefix', 'otp'),
                period: config('otp-handler.period', 600),
                digits: config('otp-handler.digits', 4),
            );
            
            $result = $generator->execute($referenceId, $mobile);
            
            $this->info("✅ OTP Generated: {$result['code']}");
            $this->line("   Reference ID: {$referenceId}");
            $this->line("   Expires at: {$result['expires_at']}");
            $this->newLine();
            
            // Send SMS
            if (!$skipSms) {
                $this->info("📤 Sending SMS...");
                
                $smsService = new SmsService(
                    provider: config('otp-handler.sms_provider', 'engagespark'),
                    senderId: config('otp-handler.engagespark.sender_id')
                );
                
                $smsService->sendOtp($mobile, $result['code'], config('otp-handler.label', 'Your App'));
                
                $this->info("✅ SMS sent to {$mobile}");
                $this->newLine();
            }
            
            // Show validation instructions
            $this->comment("To validate the OTP:");
            $this->line("php artisan test:otp {$mobile} --validate={$result['code']}");
            $this->newLine();
            
            $this->comment("Or test with wrong code:");
            $this->line("php artisan test:otp {$mobile} --validate=9999");
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed: " . $e->getMessage());
            
            if ($this->output->isVerbose()) {
                $this->newLine();
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            }
            
            return self::FAILURE;
        }
    }
    
    protected function validateOtp(string $referenceId, string $code): int
    {
        $this->info("🔍 Validating OTP: {$code}");
        
        try {
            $validator = new ValidateOtp(
                cachePrefix: config('otp-handler.cache_prefix', 'otp'),
                period: config('otp-handler.period', 600),
                digits: config('otp-handler.digits', 4),
            );
            
            $isValid = $validator->execute($referenceId, $code);
            
            if ($isValid) {
                $this->info("✅ OTP is valid!");
                return self::SUCCESS;
            } else {
                $this->error("❌ OTP is invalid or expired");
                return self::FAILURE;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Validation failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
