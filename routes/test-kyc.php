<?php

use Illuminate\Support\Facades\Route;

/**
 * Test routes for KYC callback testing
 *
 * Usage:
 * GET /test-kyc-callback/{flow_id}?status=auto_approved&transactionId=TEST-123
 */
Route::prefix('test-kyc')->group(function () {
    Route::get('/callback/{flow_id}', function (string $flow_id) {
        $status = request()->query('status', 'auto_approved');
        $transactionId = request()->query('transactionId', 'TEST-'.time());

        \Log::info('[TEST] Simulating KYC callback', [
            'flow_id' => $flow_id,
            'status' => $status,
            'transaction_id' => $transactionId,
        ]);

        // Redirect to actual callback
        return redirect()->route('form-flow.kyc.callback', [
            'flow_id' => $flow_id,
            'status' => $status,
            'transactionId' => $transactionId,
        ]);
    })->name('test-kyc.callback');

    Route::get('/link/{flow_id}', function (string $flow_id) {
        $kycData = session("form_flow.{$flow_id}.kyc");

        return response()->json([
            'flow_id' => $flow_id,
            'kyc_data' => $kycData,
            'test_callback_url' => route('test-kyc.callback', [
                'flow_id' => $flow_id,
                'status' => 'auto_approved',
                'transactionId' => $kycData['transaction_id'] ?? 'TEST-123',
            ]),
        ]);
    })->name('test-kyc.link');
});
