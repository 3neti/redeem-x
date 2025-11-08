<?php

declare(strict_types=1);

namespace App\Actions\Api\Contacts;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowContact
{
    use AsAction;

    public function asController(): JsonResponse
    {
        return ApiResponse::error('Not implemented yet', 501);
    }
}
