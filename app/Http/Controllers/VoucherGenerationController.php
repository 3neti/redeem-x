<?php

namespace App\Http\Controllers;

use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\ExternalMetadataData;
use App\Http\Requests\VoucherGenerationRequest;
use App\Actions\CalculateChargeAction;
use App\Models\VoucherGenerationCharge;
use App\Services\ReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class VoucherGenerationController extends Controller
{
    /**
     * Show the voucher generation form.
     */
    public function create(): Response
    {
        return Inertia::render('vouchers/Generate/Create', [
            'input_field_options' => $this->getInputFieldOptions(),
            'config' => config('generate'),
        ]);
    }

    /**
     * Generate vouchers based on instructions.
     */
    public function store(
        VoucherGenerationRequest $request,
        CalculateChargeAction $calculateCharge,
        ReconciliationService $reconciliation
    ): RedirectResponse {
        $instructions = $request->toInstructions();
        $user = auth()->user();

        // Check reconciliation before generation
        $totalAmount = $instructions->cash->amount * $instructions->count;
        
        if ($reconciliation->shouldBlockGeneration($totalAmount)) {
            Log::warning('[VoucherGeneration] Blocked due to insufficient bank balance', [
                'user_id' => $user->id,
                'requested_amount' => $totalAmount,
                'available' => $reconciliation->getAvailableAmount(
                    $reconciliation->getBankBalance()
                ),
            ]);

            return back()->withErrors([
                'amount' => $reconciliation->getGenerationLimitMessage(),
            ]);
        }
        
        return DB::transaction(function () use ($instructions, $user, $calculateCharge, $request) {
            // Generate vouchers
            $vouchers = GenerateVouchers::run($instructions);
            $count = $vouchers->count();
            
            // Calculate charges
            $breakdown = $calculateCharge->handle($user, $instructions);
            
            // Create charge record
            $charge = VoucherGenerationCharge::create([
                'user_id' => $user->id,
                'campaign_id' => null, // Could be linked if generation was from campaign
                'voucher_codes' => $vouchers->pluck('code')->toArray(),
                'voucher_count' => $count,
                'instructions_snapshot' => $instructions->toArray(),
                'charge_breakdown' => $breakdown->breakdown,
                'total_charge' => $breakdown->total / 100, // Convert from centavos to decimal
                'charge_per_voucher' => ($breakdown->total / $count) / 100,
                'generated_at' => now(),
            ]);
            
            // Set external metadata if provided
            $externalMetadata = $this->getExternalMetadata($request);
            
            // Link vouchers to user and set metadata
            foreach ($vouchers as $voucher) {
                $user->generatedVouchers()->attach($voucher->code, [
                    'generated_at' => now(),
                ]);
                
                // Set external metadata on voucher
                if ($externalMetadata) {
                    $voucher->external_metadata = $externalMetadata;
                    $voucher->save();
                }
            }
            
            return redirect()
                ->route('vouchers.generate.success', ['count' => $count])
                ->with('success', sprintf(
                    'Successfully generated %d voucher%s',
                    $count,
                    $count > 1 ? 's' : ''
                ));
        });
    }

    /**
     * Show voucher generation success page.
     */
    public function success(int $count): Response
    {
        // Get the latest N vouchers for this user
        $vouchers = auth()->user()
            ->vouchers()
            ->latest()
            ->take($count)
            ->get();

        if ($vouchers->isEmpty()) {
            abort(404, 'No vouchers found');
        }

        return Inertia::render('vouchers/Generate/Success', [
            'vouchers' => $vouchers->map(function($voucher) {
                return [
                    'id' => $voucher->id,
                    'code' => $voucher->code,
                    'amount' => $voucher->instructions->cash->amount ?? 0,
                    'currency' => $voucher->instructions->cash->currency ?? 'PHP',
                    'status' => $voucher->status ?? 'active',
                    'expires_at' => $voucher->expires_at?->toIso8601String(),
                    'created_at' => $voucher->created_at->toIso8601String(),
                ];
            }),
            'batch_id' => 'batch-' . now()->timestamp,
            'count' => $vouchers->count(),
            'total_value' => $vouchers->sum(function($v) {
                return $v->instructions->cash->amount ?? 0;
            }),
        ]);
    }

    /**
     * Get input field options for the form.
     */
    protected function getInputFieldOptions(): array
    {
        return \LBHurtado\Voucher\Enums\VoucherInputField::options();
    }
    
    /**
     * Get external metadata from request if provided.
     */
    protected function getExternalMetadata(VoucherGenerationRequest $request): ?ExternalMetadataData
    {
        $metadata = $request->validated('external_metadata');
        
        if (empty($metadata)) {
            return null;
        }
        
        return ExternalMetadataData::from($metadata);
    }
}
