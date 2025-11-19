<?php

declare(strict_types=1);

namespace App\Data;

use LBHurtado\Contact\Models\Contact;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Illuminate\Support\Carbon;

/**
 * Individual deposit transaction from a sender.
 * 
 * Represents a single payment received from a sender contact,
 * extracted from the pivot table metadata.
 */
class DepositTransactionData extends Data
{
    public function __construct(
        public ?int $sender_id,
        public ?string $sender_name,
        public ?string $sender_mobile,
        public float $amount,
        public string $currency,
        public ?string $institution,
        public ?string $institution_name,
        public ?string $operation_id,
        public ?string $channel,
        public ?string $reference_number,
        public ?string $transfer_type,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $timestamp,
    ) {}

    /**
     * Create from metadata array stored in pivot table.
     */
    public static function fromMetadata(Contact $sender, array $metadata): static
    {
        return new static(
            sender_id: $sender->id,
            sender_name: $sender->name,
            sender_mobile: $sender->mobile,
            amount: (float) ($metadata['amount'] ?? 0),
            currency: $metadata['currency'] ?? 'PHP',
            institution: $metadata['institution'] ?? 'UNKNOWN',
            institution_name: Contact::institutionName($metadata['institution'] ?? 'UNKNOWN'),
            operation_id: $metadata['operation_id'] ?? null,
            channel: $metadata['channel'] ?? null,
            reference_number: $metadata['reference_number'] ?? null,
            transfer_type: $metadata['transfer_type'] ?? null,
            timestamp: isset($metadata['timestamp']) 
                ? Carbon::parse($metadata['timestamp'])
                : null,
        );
    }
}
