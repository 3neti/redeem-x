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
        // Normalize to E.164 — auto-detect country, fall back to PH for national format
        try {
            $e164 = phone($value)->formatE164();
        } catch (\Throwable) {
            try {
                $e164 = phone($value, 'PH')->formatE164();
            } catch (\Throwable) {
                $e164 = $value;
            }
        }

        $query = User::where('mobile', $e164);

        if ($this->ignoreUserId) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('This mobile number is already registered.');
        }
    }
}
