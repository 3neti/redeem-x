<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\TopUp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;

class TopUpController extends Controller
{
    private const DEBUG = false;
    
    /**
     * Display top-up page.
     */
    public function index()
    {
        $user = auth()->user();
        
        $recentTopUps = $user->getTopUps()->take(5)->map(fn($topUp) => [
            'reference_no' => $topUp->reference_no,
            'amount' => $topUp->amount,
            'status' => $topUp->payment_status,
            'gateway' => $topUp->gateway,
            'institution_code' => $topUp->institution_code,
            'created_at' => $topUp->created_at->toIso8601String(),
        ]);
        
        $pendingTopUps = $user->getPendingTopUps()->map(fn($topUp) => [
            'reference_no' => $topUp->reference_no,
            'amount' => $topUp->amount,
            'status' => $topUp->payment_status,
            'gateway' => $topUp->gateway,
            'institution_code' => $topUp->institution_code,
            'created_at' => $topUp->created_at->toIso8601String(),
        ]);
        
        return Inertia::render('wallet/TopUp', [
            'balance' => $user->balanceFloat,
            'recentTopUps' => $recentTopUps,
            'pendingTopUps' => $pendingTopUps,
        ]);
    }

    /**
     * Initiate a new top-up.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
            'gateway' => ['required', 'string', 'in:netbank'],
            'institution_code' => ['nullable', 'string'],
        ]);

        try {
            $user = auth()->user();
            
            $result = $user->initiateTopUp(
                amount: (float) $validated['amount'],
                gateway: $validated['gateway'],
                institutionCode: $validated['institution_code'] ?? null
            );

            if (self::DEBUG) {
                Log::info('[TopUp] Initiated successfully', [
                    'user_id' => $user->id,
                    'reference_no' => $result->reference_no,
                    'amount' => $result->amount,
                ]);
            }
            
            // Auto-confirm in fake mode if configured
            $useFake = config('payment-gateway.netbank.direct_checkout.use_fake', false);
            $autoConfirm = config('payment-gateway.top_up.auto_confirm_fake', false);
            
            if ($useFake && $autoConfirm) {
                $topUp = TopUp::where('reference_no', $result->reference_no)->first();
                if ($topUp && !$topUp->isPaid()) {
                    $topUp->markAsPaid('FAKE-AUTO-' . now()->timestamp);
                    $user->creditWalletFromTopUp($topUp);
                    
                    if (self::DEBUG) {
                        Log::info('[TopUp] Auto-confirmed (fake mode)', [
                            'reference_no' => $topUp->reference_no,
                            'new_balance' => $user->fresh()->balanceFloat,
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'redirect_url' => $result->redirect_url,
                'reference_no' => $result->reference_no,
            ]);
        } catch (TopUpException $e) {
            Log::error('[TopUp] Initiation failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle callback after payment.
     */
    public function callback(Request $request)
    {
        $referenceNo = $request->query('reference_no');
        
        if (!$referenceNo) {
            return redirect()->route('topup.index')
                ->with('error', 'Invalid callback');
        }

        try {
            $topUp = TopUp::where('reference_no', $referenceNo)->firstOrFail();
            
            return Inertia::render('wallet/TopUpCallback', [
                'topUp' => [
                    'reference_no' => $topUp->reference_no,
                    'amount' => $topUp->amount,
                    'status' => $topUp->payment_status,
                    'gateway' => $topUp->gateway,
                    'institution_code' => $topUp->institution_code,
                    'created_at' => $topUp->created_at->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('[TopUp] Callback error', [
                'reference_no' => $referenceNo,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('topup.index')
                ->with('error', 'Top-up not found');
        }
    }

    /**
     * Check top-up status (polling endpoint).
     */
    public function status(string $referenceNo)
    {
        try {
            $topUp = TopUp::where('reference_no', $referenceNo)->firstOrFail();
            
            return response()->json([
                'status' => $topUp->payment_status,
                'paid_at' => $topUp->paid_at?->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Top-up not found',
            ], 404);
        }
    }
}
