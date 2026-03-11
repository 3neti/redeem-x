<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueMobile implements ValidationRule
{
    public function __construct(
        protected ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $phone = phone($value, 'PH');
            $candidates = array_unique(array_filter([
                $value,
                $phone->formatE164(),                          // +639173011987
                ltrim($phone->formatE164(), '+'),              // 639173011987
                $phone->formatForMobileDialingInCountry('PH'), // 09173011987
            ]));
        } catch (\Throwable) {
            // Not parseable — just check exact match
            $candidates = [$value];
        }

        $query = User::whereIn('mobile', $candidates);

        if ($this->ignoreUserId) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('This mobile number is already registered.');
        }
    }
}
