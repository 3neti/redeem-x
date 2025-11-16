<?php

declare(strict_types=1);

namespace App\Actions\Api\Senders;

use App\Data\DepositTransactionData;
use App\Data\SenderData;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use LBHurtado\Contact\Models\Contact;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\DataCollection;

/**
 * Show sender details with transaction history via API.
 *
 * Endpoint: GET /api/v1/senders/{id}
 */
class ShowSender
{
    use AsAction;

    public function asController(ActionRequest $request, int $contactId): JsonResponse
    {
        $user = $request->user();

        // Find the sender contact
        $sender = $user->senders()->find($contactId);

        if (!$sender) {
            return ApiResponse::error('Sender not found', 404);
        }

        // Get pivot data
        $pivot = $sender->pivot;
        $metadata = is_string($pivot->metadata) 
            ? json_decode($pivot->metadata, true) 
            : ($pivot->metadata ?? []);

        // Transform to SenderData DTO
        $senderData = SenderData::fromContactWithPivot($sender, $user);

        // Build transaction history
        $transactions = collect();
        if (is_array($metadata)) {
            foreach ($metadata as $txMetadata) {
                $txMetadata['amount'] = $txMetadata['amount'] ?? 0;
                $txMetadata['currency'] = 'PHP';
                
                $transactions->push(
                    DepositTransactionData::fromMetadata($sender, $txMetadata)
                );
            }
        }

        // Sort by timestamp desc
        $transactions = $transactions->sortByDesc('timestamp')->values();

        return ApiResponse::success([
            'sender' => $senderData,
            'transactions' => new DataCollection(DepositTransactionData::class, $transactions),
        ]);
    }
}
