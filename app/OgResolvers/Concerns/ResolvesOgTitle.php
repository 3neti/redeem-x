<?php

declare(strict_types=1);

namespace App\OgResolvers\Concerns;

use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Models\Voucher;

trait ResolvesOgTitle
{
    /**
     * Resolve the OG title based on the rider's og_source preference.
     *
     * rider.message is always used for og:title — unless og_source is 'message',
     * in which case the message is rendered on the image card and the title
     * reverts to the status default to avoid duplication.
     */
    protected function resolveOgTitle(RiderInstructionData $rider, string $status, Voucher $voucher): string
    {
        if ($rider->og_source === 'message') {
            return $this->defaultTitle($status);
        }

        return $rider->message ?? $this->defaultTitle($status);
    }
}
