<?php

declare(strict_types=1);

namespace App\Data\Api\Auth;

use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * Authenticated user data transfer object.
 * 
 * Represents the authenticated user with basic profile information.
 */
class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $mobile,
        public ?string $avatar,
        /** @var string[] */
        public ?array $current_token_abilities = null,
    ) {}

    /**
     * Create from User model.
     */
    public static function fromUser(User $user, ?array $tokenAbilities = null): static
    {
        return new static(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            mobile: $user->mobile,
            avatar: $user->avatar,
            current_token_abilities: $tokenAbilities,
        );
    }
}
