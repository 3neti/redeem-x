<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Settings\VoucherSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Enums\VoucherType;

class PwaVoucherController extends Controller
{
    /**
     * Display voucher list.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $query = $user->vouchers()->latest();

        // Status filters
        match($filter) {
            'active' => $query->where('state', 'active')->whereNull('redeemed_at'),
            'redeemed' => $query->whereNotNull('redeemed_at'),
            'expired' => $query->where(function($q) {
                $q->where('expires_at', '<', now())
                  ->where('state', 'active');
            }),
            'locked' => $query->where('state', 'locked'),
            'cancelled' => $query->where('state', 'cancelled'),
            'closed' => $query->where('state', 'closed'),
            // Type filters
            'type-redeemable' => $query->where('voucher_type', 'redeemable'),
            'type-payable' => $query->where('voucher_type', 'payable'),
            'type-settlement' => $query->where('voucher_type', 'settlement'),
            default => null, // 'all' - no filter
        };

        $vouchers = $query->paginate(20)->through(function ($voucher) {
            // Helper to extract numeric amount from Money object or number
            $extractAmount = function ($value) {
                if (is_object($value) && method_exists($value, 'getAmount')) {
                    // Money->getAmount() returns BigDecimal, use toFloat() for conversion
                    return $value->getAmount()->toFloat();
                }
                return is_numeric($value) ? (float) $value : 0;
            };
            
            // Determine amount based on voucher type
            $amount = match($voucher->voucher_type->value) {
                'payable' => $voucher->target_amount ?? 0,
                'settlement' => $extractAmount($voucher->cash?->amount), // Show loan amount
                default => $extractAmount($voucher->cash?->amount), // Redeemable
            };
            $amountFloat = is_numeric($amount) ? (float) $amount : 0;
            
            return [
                'code' => $voucher->code,
                'amount' => $amountFloat,
                'target_amount' => $voucher->target_amount ? (float) $voucher->target_amount : null,
                'voucher_type' => $voucher->voucher_type->value,
                'currency' => $voucher->cash?->currency ?? 'PHP',
                'status' => $voucher->status,
                'state' => $voucher->state?->value,
                'expires_at' => $voucher->expires_at?->toIso8601String(),
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                'created_at' => $voucher->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('pwa/Vouchers/Index', [
            'vouchers' => $vouchers,
            'filter' => $filter,
        ]);
    }

    /**
     * Display voucher detail with QR and share options.
     */
    public function show(Request $request, string $code): Response
    {
        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->with(['owner', 'inputs', 'redeemers'])
            ->firstOrFail();

        // Helper to extract numeric amount from Money object or number
        $extractAmount = function ($value) {
            if (is_object($value) && method_exists($value, 'getAmount')) {
                // Money->getAmount() returns BigDecimal, use toFloat() for conversion
                return $value->getAmount()->toFloat();
            }
            return is_numeric($value) ? (float) $value : 0;
        };
        
        // Determine amount based on voucher type
        $amount = match($voucher->voucher_type->value) {
            'payable' => $voucher->target_amount ?? 0,
            'settlement' => $extractAmount($voucher->cash?->amount), // Show loan amount
            default => $extractAmount($voucher->cash?->amount), // Redeemable
        };
        $amountFloat = is_numeric($amount) ? (float) $amount : 0;
        
        // Get collected form flow data from redeemer metadata if voucher has been redeemed
        $collectedData = null;
        if ($voucher->isRedeemed()) {
            $redeemer = $voucher->redeemers->first();
            if ($redeemer && isset($redeemer->metadata['redemption']['inputs']['_form_flow_collected_data'])) {
                $collectedJson = $redeemer->metadata['redemption']['inputs']['_form_flow_collected_data'];
                $collectedData = json_decode($collectedJson, true);
            }
        }
        
        // Get wallet transactions for this voucher
        $walletTransactions = [];
        if ($voucher->cash && $voucher->cash->wallet) {
            $disbursementMeta = $voucher->metadata['disbursement'] ?? null;
            $redeemer = $voucher->redeemers->first();
            $contact = $redeemer?->redeemer;
            
            $walletTransactions = $voucher->cash->wallet->transactions()
                ->where(function ($query) use ($voucher) {
                    $query->whereJsonContains('meta->voucher_code', $voucher->code)
                          ->orWhere(function ($q) use ($voucher) {
                              // Redemption transactions for THIS voucher
                              $q->where('type', 'withdraw')
                                ->whereJsonContains('meta->flow', 'redeem')
                                ->whereJsonContains('meta->voucher_code', $voucher->code);
                          })
                          ->orWhere(function ($q) use ($voucher) {
                              // Payment transactions for THIS voucher
                              $q->where('type', 'deposit')
                                ->whereJsonContains('meta->flow', 'pay')
                                ->whereJsonContains('meta->voucher_code', $voucher->code);
                          });
                })
                ->latest()
                ->get()
                ->map(function ($tx) use ($disbursementMeta, $contact) {
                    $meta = $tx->meta ?? [];
                    
                    // Enhance withdrawal (redemption) transactions with disbursement data
                    if ($tx->type === 'withdraw' && ($meta['flow'] ?? null) === 'redeem' && $disbursementMeta) {
                        $meta['recipient_name'] = $disbursementMeta['recipient_name'] ?? null;
                        $meta['recipient_identifier'] = $disbursementMeta['recipient_identifier'] ?? null;
                        $meta['bank_code'] = $disbursementMeta['metadata']['bank_code'] ?? null;
                        $meta['bank_name'] = $disbursementMeta['metadata']['bank_name'] ?? null;
                        $meta['settlement_rail'] = $disbursementMeta['settlement_rail'] ?? null;
                    }
                    
                    // Add contact mobile if available
                    if ($contact && $contact->mobile) {
                        $meta['contact_mobile'] = $contact->mobile;
                    }
                    
                    return [
                        'id' => $tx->id,
                        'uuid' => $tx->uuid,
                        'type' => $tx->type,
                        'amount' => $tx->amount / 100, // Convert to major units (pesos)
                        'currency' => 'PHP',
                        'confirmed' => $tx->confirmed,
                        'meta' => $meta,
                        'created_at' => $tx->created_at->toIso8601String(),
                    ];
                })->toArray();
        }
        
        $data = [
            'voucher' => [
                'code' => $voucher->code,
                'amount' => $amountFloat,
                'target_amount' => $voucher->target_amount ? (float) $voucher->target_amount : null,
                'voucher_type' => $voucher->voucher_type->value,
                'currency' => $voucher->cash?->currency ?? 'PHP',
                'status' => $voucher->status,
                'state' => $voucher->state?->value,
                'created_at' => $voucher->created_at->toIso8601String(),
                'starts_at' => $voucher->starts_at?->toIso8601String(),
                'expires_at' => $voucher->expires_at?->toIso8601String(),
                'redeemed_at' => $voucher->redeemed_at?->toIso8601String(),
                'locked_at' => $voucher->locked_at?->toIso8601String(),
                'closed_at' => $voucher->closed_at?->toIso8601String(),
                'redeem_url' => $this->buildRedemptionUrl($voucher),
                
                // Full voucher data for details sheet
                'full_data' => array_merge(
                    VoucherData::fromModel($voucher)->toArray(),
                    [
                        'collected_data' => $collectedData,
                        'wallet_transactions' => $walletTransactions,
                    ]
                ),
            ],
            'input_field_options' => VoucherInputField::options(),
        ];

        // Add settlement data for payable/settlement vouchers
        if ($voucher->voucher_type && in_array($voucher->voucher_type->value, ['payable', 'settlement'])) {
            $data['settlement'] = [
                'type' => $voucher->voucher_type->value,
                'state' => $voucher->state->value,
                'target_amount' => $voucher->target_amount,
                'paid_total' => $voucher->getPaidTotal(),
                'redeemed_total' => $voucher->getRedeemedTotal(),
                'remaining' => $voucher->getRemaining(),
                'available_balance' => $voucher->cash?->balanceFloat ?? 0,
                'can_accept_payment' => $voucher->canAcceptPayment(),
                'can_redeem' => $voucher->canRedeem(),
                'is_locked' => $voucher->isLocked(),
                'is_closed' => $voucher->isClosed(),
                'is_expired' => $voucher->isExpired(),
            ];
        }

        // Add envelope data if exists
        if (method_exists($voucher, 'envelope')) {
            $envelope = $voucher->envelope;
            if ($envelope) {
                $envelope->load(['checklistItems', 'attachments', 'signals', 'auditLogs']);
                
                $data['envelope'] = [
                    'id' => $envelope->id,
                    'reference_code' => $envelope->reference_code,
                    'driver_id' => $envelope->driver_id,
                    'driver_version' => $envelope->driver_version,
                    'status' => $envelope->status->value,
                    'payload' => $envelope->payload,
                    'created_at' => $envelope->created_at->toIso8601String(),
                    'updated_at' => $envelope->updated_at->toIso8601String(),
                    
                    'attachments' => $envelope->attachments->map(fn ($att) => [
                        'id' => $att->id,
                        'doc_type' => $att->doc_type,
                        'original_filename' => $att->original_filename,
                        'mime_type' => $att->mime_type,
                        'size' => $att->size ?? null,
                        'review_status' => $att->review_status,
                        'url' => $att->file_path ? \Storage::disk($att->disk ?? 'public')->url($att->file_path) : null,
                        'created_at' => $att->created_at->toIso8601String(),
                    ])->toArray(),
                    
                    'audit_logs' => $envelope->auditLogs->sortBy('id')->values()->map(fn ($log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'actor_email' => $log->actor?->email ?? null,
                        'created_at' => $log->created_at->toIso8601String(),
                    ])->toArray(),
                ];
            }
        }
        
        return Inertia::render('pwa/Vouchers/Show', $data);
    }

    /**
     * Build dynamic redemption URL based on voucher type and settings.
     */
    private function buildRedemptionUrl($voucher): string
    {
        $settings = app(VoucherSettings::class);
        $baseUrl = rtrim(config('app.url'), '/');
        $code = $voucher->code;

        $endpoint = match($voucher->voucher_type) {
            VoucherType::PAYABLE, VoucherType::SETTLEMENT => 
                $settings->default_settlement_endpoint,
            default => 
                $settings->default_redemption_endpoint,
        };

        return "{$baseUrl}{$endpoint}?code={$code}";
    }

    /**
     * Show voucher generation form.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        
        // Load user campaigns
        $campaigns = $user ? Campaign::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(fn($campaign) => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'slug' => $campaign->slug,
                'instructions' => $campaign->instructions->toArray(),
            ])
            ->toArray() : [];
        
        // Load input field options from VoucherInputField enum
        $inputFieldOptions = VoucherInputField::options();
        
        // Get wallet balance (in major currency - pesos)
        $walletBalance = $user ? (float) $user->balanceFloat : 0.0;
        $formattedBalance = '₱' . number_format($walletBalance, 2);
        
        // Load envelope drivers
        $envelopeDrivers = [];
        try {
            $driverService = app(\LBHurtado\SettlementEnvelope\Services\DriverService::class);
            $driverList = $driverService->list();
            $envelopeDrivers = collect($driverList)->map(function ($item) use ($driverService) {
                try {
                    $driver = $driverService->load($item['id'], $item['version']);
                    return [
                        'id' => $driver->id,
                        'version' => $driver->version,
                        'title' => $driver->title,
                        'description' => $driver->description,
                        'key' => $driver->id . '@' . $driver->version, // For dropdown value
                    ];
                } catch (\Exception $e) {
                    return null;
                }
            })->filter()->values()->all();
        } catch (\Exception $e) {
            // Silently fail if driver service unavailable
        }
        
        return Inertia::render('pwa/Vouchers/Generate', [
            'campaigns' => $campaigns,
            'inputFieldOptions' => $inputFieldOptions,
            'walletBalance' => $walletBalance,
            'formattedBalance' => $formattedBalance,
            'envelopeDrivers' => $envelopeDrivers,
        ]);
    }

    /**
     * Store generated vouchers.
     */
    public function store(Request $request)
    {
        // TODO: Implement voucher generation
        // This will call the existing GenerateVouchers action
        return back()->with('success', 'Vouchers generated successfully');
    }

    /**
     * Lock a voucher.
     */
    public function lock(Request $request, string $code)
    {
        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->firstOrFail();

        $voucher->update(['state' => \LBHurtado\Voucher\Enums\VoucherState::LOCKED]);

        // Return redirect to force Inertia to reload props
        return redirect()->back()->with('success', 'Voucher locked successfully');
    }

    /**
     * Unlock a voucher.
     */
    public function unlock(Request $request, string $code)
    {
        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->firstOrFail();

        $voucher->update(['state' => \LBHurtado\Voucher\Enums\VoucherState::ACTIVE]);

        return redirect()->back()->with('success', 'Voucher unlocked successfully');
    }

    /**
     * Close a voucher.
     */
    public function close(Request $request, string $code)
    {
        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->firstOrFail();

        $voucher->update(['state' => \LBHurtado\Voucher\Enums\VoucherState::CLOSED]);

        return redirect()->back()->with('success', 'Voucher closed successfully');
    }

    /**
     * Cancel a voucher.
     */
    public function cancel(Request $request, string $code)
    {
        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->firstOrFail();

        $voucher->update([
            'state' => \LBHurtado\Voucher\Enums\VoucherState::CANCELLED,
            'expires_at' => now(), // Also set expiration for backward compatibility
        ]);

        return redirect()->back()->with('success', 'Voucher cancelled successfully');
    }

    /**
     * Invalidate a voucher (alias for cancel).
     * @deprecated Use cancel() instead
     */
    public function invalidate(Request $request, string $code)
    {
        return $this->cancel($request, $code);
    }

    /**
     * Extend voucher expiration.
     */
    public function extendExpiration(Request $request, string $code)
    {
        $validated = $request->validate([
            'extension_type' => 'required|in:hours,days,weeks,months,years,date',
            'extension_value' => 'required_unless:extension_type,date|integer|min:1',
            'new_date' => 'required_if:extension_type,date|date|after:now',
        ]);

        $voucher = $request->user()
            ->vouchers()
            ->where('code', $code)
            ->firstOrFail();

        // Calculate new expiration date
        $currentExpiration = $voucher->expires_at ?? now();
        
        if ($validated['extension_type'] === 'date') {
            $newExpiration = \Carbon\Carbon::parse($validated['new_date']);
        } else {
            $newExpiration = match($validated['extension_type']) {
                'hours' => $currentExpiration->addHours($validated['extension_value']),
                'days' => $currentExpiration->addDays($validated['extension_value']),
                'weeks' => $currentExpiration->addWeeks($validated['extension_value']),
                'months' => $currentExpiration->addMonths($validated['extension_value']),
                'years' => $currentExpiration->addYears($validated['extension_value']),
            };
        }

        $voucher->update(['expires_at' => $newExpiration]);

        return back()->with('success', 'Voucher expiration extended successfully');
    }
}
