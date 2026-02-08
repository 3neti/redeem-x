<?php

declare(strict_types=1);

namespace App\Actions\Api\BankAccounts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Set a bank account as default for the authenticated user.
 *
 * Endpoint: PUT /api/v1/user/bank-accounts/{id}/set-default
 */
class SetDefaultBankAccount
{
    use AsAction;

    public function asController(ActionRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        $success = $user->setDefaultBankAccount($id);

        if (! $success) {
            return ApiResponse::notFound('Bank account not found.');
        }

        return ApiResponse::success([
            'message' => 'Default bank account updated successfully.',
            'bank_account' => $user->getDefaultBankAccount(),
        ]);
    }
}
