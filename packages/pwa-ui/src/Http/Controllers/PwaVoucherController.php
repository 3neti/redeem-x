<?php

namespace LBHurtado\PwaUi\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Settings\VoucherSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
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
        
        return Inertia::render('pwa/Vouchers/Show', [
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
            ],
        ]);
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
        $walletBalance = $user ? $user->balanceFloat : 0;
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
