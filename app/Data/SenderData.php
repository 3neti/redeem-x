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
 * Sender contact with transaction statistics.
 * 
 * Represents a contact who has sent money to the user,
 * including cumulative stats and payment method history.
 */
class SenderData extends Data
{
    public function __construct(
        public int $id,
        public string $mobile,
        public string $name,
        public float $total_sent,
        public int $transaction_count,
        /** @var string[] */
        public array $institutions_used,
        public ?string $latest_institution,
        public ?string $latest_institution_name,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $first_transaction_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $last_transaction_at,
    ) {}

    /**
     * Create from Contact model with pivot data for a specific user.
     */
    public static function fromContactWithPivot(Contact $contact, $user): static
    {
        // Get pivot data for this user
        $pivot = $contact->recipients()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;

        $institutionsUsed = $contact->institutionsUsed($user);
        $latestInstitution = $contact->latestInstitution($user);

        return new static(
            id: $contact->id,
            mobile: $contact->mobile,
            name: $contact->name,
            total_sent: (float) ($pivot?->total_sent ?? 0),
            transaction_count: (int) ($pivot?->transaction_count ?? 0),
            institutions_used: $institutionsUsed,
            latest_institution: $latestInstitution,
            latest_institution_name: $latestInstitution 
                ? Contact::institutionName($latestInstitution)
                : null,
            first_transaction_at: $pivot?->first_transaction_at,
            last_transaction_at: $pivot?->last_transaction_at,
        );
    }
}
