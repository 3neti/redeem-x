<?php

declare(strict_types=1);

namespace App\Data\Api\Auth;

use Laravel\Sanctum\PersonalAccessToken;
use Spatie\LaravelData\Attributes\{WithCast, WithTransformer};
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Illuminate\Support\Carbon;

/**
 * API Token data transfer object.
 * 
 * Represents a Sanctum personal access token with its metadata.
 */
class TokenData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        /** @var string[] */
        public array $abilities,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $last_used_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $expires_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public Carbon $created_at,
        public ?string $plain_text_token = null,
    ) {}

    /**
     * Create from PersonalAccessToken model.
     */
    public static function fromToken(PersonalAccessToken $token, ?string $plainTextToken = null): static
    {
        return new static(
            id: $token->id,
            name: $token->name,
            abilities: $token->abilities ?? ['*'],
            last_used_at: $token->last_used_at,
            expires_at: $token->expires_at,
            created_at: $token->created_at,
            plain_text_token: $plainTextToken,
        );
    }
}
