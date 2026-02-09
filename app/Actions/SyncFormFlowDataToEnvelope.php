<?php

declare(strict_types=1);

namespace App\Actions;

use LBHurtado\SettlementEnvelope\Actions\SyncFormFlowToEnvelope;
use LBHurtado\SettlementEnvelope\Data\FormFlowSyncResultData;
use LBHurtado\Voucher\Models\Voucher;

/**
 * Sync form flow collected data to a voucher's settlement envelope.
 *
 * This is a thin wrapper around the package's SyncFormFlowToEnvelope action
 * that extracts the envelope from the Voucher model.
 */
class SyncFormFlowDataToEnvelope
{
    public function __construct(
        protected SyncFormFlowToEnvelope $syncAction
    ) {}

    /**
     * Execute the sync operation.
     *
     * @param  Voucher  $voucher  The voucher with the envelope
     * @param  array  $collectedData  Form flow collected data (step-indexed or numeric-indexed)
     * @return FormFlowSyncResultData Result containing counts and any errors
     */
    public function execute(Voucher $voucher, array $collectedData): FormFlowSyncResultData
    {
        $envelope = $voucher->envelope;

        if (! $envelope) {
            return FormFlowSyncResultData::failure('Voucher has no settlement envelope');
        }

        return $this->syncAction->execute($envelope, $collectedData, $voucher->code);
    }
}

/**
 * @deprecated Use LBHurtado\SettlementEnvelope\Data\FormFlowSyncResultData instead
 */
class_alias(FormFlowSyncResultData::class, 'App\Actions\SyncResult');
