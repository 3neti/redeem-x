<?php

declare(strict_types=1);

namespace LBHurtado\MessagingBot\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TxtcmdrClient
{
    protected string $baseUrl;

    protected string $apiToken;

    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('otp-handler.txtcmdr.base_url', 'http://txtcmdr.test');
        $this->apiToken = config('otp-handler.txtcmdr.api_token', '');
        $this->timeout = (int) config('otp-handler.txtcmdr.timeout', 30);
    }

    /**
     * Request a new OTP code.
     *
     * @return array{verification_id: string, expires_in: int, dev_code: string|null}
     */
    public function requestOtp(string $mobile, ?string $externalRef = null): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiToken)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/otp/request", [
                    'mobile' => $mobile,
                    'purpose' => 'redemption',
                    'external_ref' => $externalRef,
                ]);

            $response->throw();

            return $response->json();
        } catch (\Exception $e) {
            Log::error('[TxtcmdrClient] OTP request failed', [
                'mobile' => $mobile,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Verify an OTP code.
     *
     * @return array{ok: bool, reason: string, attempts?: int, status?: string}
     */
    public function verifyOtp(string $verificationId, string $code): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiToken)
                ->acceptJson()
                ->post("{$this->baseUrl}/api/otp/verify", [
                    'verification_id' => $verificationId,
                    'code' => $code,
                ]);

            $response->throw();

            return $response->json();
        } catch (\Exception $e) {
            Log::error('[TxtcmdrClient] OTP verification failed', [
                'verification_id' => $verificationId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
