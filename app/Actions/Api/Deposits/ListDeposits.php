<?php

declare(strict_types=1);

namespace App\Actions\Api\Deposits;

use App\Data\DepositTransactionData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * List incoming deposits via API.
 *
 * Endpoint: GET /api/v1/deposits
 */
class ListDeposits
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min($request->integer('per_page', 20), 100);
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $search = $request->input('search');
        $institution = $request->input('institution');

        // Collect all deposit transactions
        $deposits = collect();
        
        // 1. Get wallet top-up transactions (deposit type with positive amount)
        $walletTransactions = $user->walletTransactions()
            ->where('type', 'deposit')
            ->where('amount', '>', 0)
            ->latest()
            ->get();
        
        foreach ($walletTransactions as $tx) {
            // Apply date filters
            if ($dateFrom && $tx->created_at < $dateFrom) continue;
            if ($dateTo && $tx->created_at > $dateTo) continue;
            
            // Apply institution filter if provided
            $txInstitution = $tx->meta['gateway'] ?? null;
            if ($institution && $txInstitution !== $institution) continue;
            
            // Extract enhanced metadata (sender info, payment method)
            $senderName = $tx->meta['sender_name'] ?? 'Wallet Top-Up';
            $senderIdentifier = $tx->meta['sender_identifier'] ?? null;
            $paymentMethod = $tx->meta['payment_method'] ?? null;
            
            $deposits->push([
                'type' => 'wallet_top_up',
                'sender' => null,
                'metadata' => [
                    'amount' => $tx->amountFloat, // Use Bavix Wallet accessor
                    'currency' => 'PHP',
                    'institution' => $txInstitution,
                    'institution_name' => ucfirst($txInstitution ?? 'Top-Up'),
                    'operation_id' => null,
                    'channel' => $tx->meta['type'] ?? 'top_up',
                    'reference_number' => $tx->meta['reference_no'] ?? null,
                    'transfer_type' => 'TOP_UP',
                    'timestamp' => $tx->created_at->toIso8601String(),
                    // Enhanced metadata
                    'sender_name' => $senderName,
                    'sender_identifier' => $senderIdentifier,
                    'payment_method' => $paymentMethod,
                ],
                'timestamp' => $tx->created_at->toIso8601String(),
            ]);
        }
        
        // 2. Get QR deposits from external senders
        $sendersQuery = $user->senders();

        // Filter by date range
        if ($dateFrom) {
            $sendersQuery->wherePivot('last_transaction_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $sendersQuery->wherePivot('last_transaction_at', '<=', $dateTo);
        }

        // Search by sender name or mobile
        if ($search) {
            $sendersQuery->where(function ($q) use ($search) {
                $q->where('mobile', 'like', "%{$search}%");
                // Note: name is an accessor, can't search directly
            });
        }

        $senders = $sendersQuery->get();
        
        foreach ($senders as $sender) {
            $pivot = $sender->pivot;
            $metadata = is_string($pivot->metadata) 
                ? json_decode($pivot->metadata, true) 
                : ($pivot->metadata ?? []);

            if (!is_array($metadata)) {
                continue;
            }

            foreach ($metadata as $txMetadata) {
                // Filter by institution if provided
                if ($institution && ($txMetadata['institution'] ?? null) !== $institution) {
                    continue;
                }

                // Add amount to metadata for DTO
                $txMetadata['amount'] = $txMetadata['amount'] ?? 0;
                $txMetadata['currency'] = 'PHP';

                $deposits->push([
                    'type' => 'qr_deposit',
                    'sender' => $sender,
                    'metadata' => $txMetadata,
                    'timestamp' => $txMetadata['timestamp'] ?? null,
                ]);
            }
        }

        // Sort by timestamp desc
        $deposits = $deposits->sortByDesc('timestamp');

        // Manual pagination
        $total = $deposits->count();
        $currentPage = max(1, $request->integer('page', 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min($currentPage, $lastPage);
        
        $offset = ($currentPage - 1) * $perPage;
        $paginatedDeposits = $deposits->slice($offset, $perPage)->values();

        // Transform to DTOs
        $depositData = $paginatedDeposits->map(function ($item) {
            if ($item['type'] === 'wallet_top_up') {
                // Create DTO for wallet top-up (no sender)
                // Use enhanced metadata if available, fallback to defaults
                $senderName = $item['metadata']['sender_name'] ?? 'Wallet Top-Up';
                $senderIdentifier = $item['metadata']['sender_identifier'] ?? null;
                $paymentMethod = $item['metadata']['payment_method'] ?? null;
                
                return DepositTransactionData::from([
                    'sender_id' => null,
                    'sender_name' => $senderName,
                    'sender_mobile' => $senderIdentifier, // Repurpose for identifier
                    'amount' => $item['metadata']['amount'],
                    'currency' => $item['metadata']['currency'],
                    'institution' => $item['metadata']['institution'],
                    'institution_name' => $item['metadata']['institution_name'],
                    'operation_id' => $item['metadata']['operation_id'],
                    'channel' => $paymentMethod ?? $item['metadata']['channel'],
                    'reference_number' => $item['metadata']['reference_number'],
                    'transfer_type' => $item['metadata']['transfer_type'],
                    'timestamp' => $item['metadata']['timestamp'],
                ]);
            }
            
            // QR deposit from sender
            return DepositTransactionData::fromMetadata($item['sender'], $item['metadata']);
        });

        return ApiResponse::success([
            'data' => new DataCollection(DepositTransactionData::class, $depositData),
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? min($offset + $perPage, $total) : null,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
                'institution' => $institution,
            ],
        ]);
    }

    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:50'],
        ];
    }
}
