<?php

declare(strict_types=1);

namespace App\Actions\Api\BankAccounts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * List all bank accounts for the authenticated user.
 *
 * Endpoint: GET /api/v1/user/bank-accounts
 */
class ListBankAccounts
{
    use AsAction;

    public function asController(ActionRequest $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'bank_accounts' => $user->getBankAccounts(),
        ]);
    }
}
