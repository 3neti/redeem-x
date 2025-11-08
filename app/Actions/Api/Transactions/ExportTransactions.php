<?php

declare(strict_types=1);

namespace App\Actions\Api\Transactions;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class ExportTransactions
{
    use AsAction;

    public function asController(): JsonResponse
    {
        return ApiResponse::error('Not implemented yet', 501);
    }
}
