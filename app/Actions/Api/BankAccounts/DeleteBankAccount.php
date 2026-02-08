<?php

declare(strict_types=1);

namespace App\Actions\Api\BankAccounts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Delete a bank account for the authenticated user.
 *
 * Endpoint: DELETE /api/v1/user/bank-accounts/{id}
 */
class DeleteBankAccount
{
    use AsAction;

    public function asController(ActionRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        $removed = $user->removeBankAccount($id);

        if (! $removed) {
            return ApiResponse::notFound('Bank account not found.');
        }

        return ApiResponse::success([
            'message' => 'Bank account deleted successfully.',
        ]);
    }
}
