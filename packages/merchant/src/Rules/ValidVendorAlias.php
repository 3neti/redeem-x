<?php

namespace LBHurtado\Merchant\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use LBHurtado\Merchant\Services\VendorAliasService;

class ValidVendorAlias implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $service = new VendorAliasService();
        
        // Normalize the alias
        $normalized = $service->normalize($value);
        
        // Validate format (3-8 chars, starts with letter, uppercase letters/digits only)
        if (!$service->validate($normalized)) {
            $minLength = config('merchant.alias.min_length', 3);
            $maxLength = config('merchant.alias.max_length', 8);
            
            $fail("The {$attribute} must be {$minLength}-{$maxLength} characters, start with a letter, and contain only uppercase letters and digits.");
            return;
        }
        
        // Check if reserved
        if ($service->isReserved($normalized)) {
            $fail("The {$attribute} '{$normalized}' is reserved and cannot be assigned.");
            return;
        }
    }
}
