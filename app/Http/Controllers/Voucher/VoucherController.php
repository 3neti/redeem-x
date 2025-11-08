<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voucher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Voucher Management Controller
 * 
 * Handles listing, viewing, and exporting vouchers for authenticated users.
 */
class VoucherController extends Controller
{
    /**
     * Display a listing of vouchers with filters.
     *
     * @param  Request  $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Voucher::query()
            ->with(['owner'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->input('status');
            
            match ($status) {
                'active' => $query->where('redeemed_at', null)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                    }),
                'redeemed' => $query->whereNotNull('redeemed_at'),
                'expired' => $query->whereNull('redeemed_at')
                    ->where('expires_at', '<=', now()),
                default => null,
            };
        }

        // Search by code
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('code', 'like', "%{$search}%");
        }

        // Paginate
        $vouchers = $query->paginate(15)->withQueryString();

        return Inertia::render('Vouchers/Index', [
            'vouchers' => VoucherData::collection($vouchers),
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ],
            'stats' => $this->getVoucherStats(),
        ]);
    }

    /**
     * Display the specified voucher.
     *
     * @param  Voucher  $voucher
     * @return Response
     */
    public function show(Voucher $voucher): Response
    {
        $voucher->load(['owner']);

        return Inertia::render('Vouchers/Show', [
            'voucher' => VoucherData::fromModel($voucher),
        ]);
    }

    /**
     * Get voucher statistics.
     *
     * @return array
     */
    protected function getVoucherStats(): array
    {
        $total = Voucher::count();
        $active = Voucher::whereNull('redeemed_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
        $redeemed = Voucher::whereNotNull('redeemed_at')->count();
        $expired = Voucher::whereNull('redeemed_at')
            ->where('expires_at', '<=', now())
            ->count();

        return compact('total', 'active', 'redeemed', 'expired');
    }
}
