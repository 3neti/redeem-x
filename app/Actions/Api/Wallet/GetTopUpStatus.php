<?php

namespace App\Actions\Api\Wallet;

use App\Data\Api\Wallet\TopUpData;
use App\Models\User;
use LBHurtado\PaymentGateway\Exceptions\TopUpException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GetTopUpStatus
{
    use AsAction;

    /**
     * Get status of a specific top-up by reference number.
     *
     * @throws TopUpException
     */
    public function handle(User $user, string $referenceNo): TopUpData
    {
        $topUp = $user->getTopUpByReference($referenceNo);

        return TopUpData::fromModel($topUp);
    }

    /**
     * Handle as controller action.
     */
    public function asController(string $referenceNo): array
    {
        try {
            $user = auth()->user();
            $topUp = $this->handle($user, $referenceNo);

            return [
                'data' => $topUp,
                'meta' => [
                    'timestamp' => now()->toIso8601String(),
                    'version' => 'v1',
                ],
            ];
        } catch (TopUpException $e) {
            throw new NotFoundHttpException($e->getMessage());
        }
    }
}
