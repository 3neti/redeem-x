<?php

namespace LBHurtado\PaymentGateway\Models;

use LBHurtado\PaymentGateway\Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Merchant.
 *
 * @property int         $id
 * @property string      $code
 * @property string      $name
 * @property string|null $city
 * @property string|null $description
 * @property string      $merchant_category_code
 * @property string|null $logo_url
 * @property bool        $allow_tip
 * @property bool        $is_dynamic
 * @property float|null  $default_amount
 * @property float|null  $min_amount
 * @property float|null  $max_amount
 * @property bool        $is_active
 *
 * @method int getKey()
 */
class Merchant extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'city',
        'description',
        'merchant_category_code',
        'logo_url',
        'allow_tip',
        'is_dynamic',
        'default_amount',
        'min_amount',
        'max_amount',
        'is_active',
    ];

    protected $casts = [
        'allow_tip' => 'boolean',
        'is_dynamic' => 'boolean',
        'is_active' => 'boolean',
        'default_amount' => 'float',
        'min_amount' => 'float',
        'max_amount' => 'float',
    ];

    public static function newFactory(): MerchantFactory
    {
        return MerchantFactory::new();
    }

    /**
     * Common merchant category codes for QR Ph.
     */
    public static function getCategoryCodes(): array
    {
        return [
            '0000' => 'General/Personal',
            '5812' => 'Eating Places/Restaurants',
            '5411' => 'Grocery Stores',
            '5712' => 'Furniture/Home Furnishings',
            '5311' => 'Department Stores',
            '7299' => 'Personal Services',
            '8099' => 'Professional Services',
            '5999' => 'Retail/Miscellaneous',
        ];
    }

    /**
     * Get the merchant category name.
     */
    public function getCategoryNameAttribute(): string
    {
        return static::getCategoryCodes()[$this->merchant_category_code] ?? 'Unknown';
    }

    /**
     * Check if merchant has amount restrictions.
     */
    public function hasAmountRestrictions(): bool
    {
        return $this->min_amount !== null || $this->max_amount !== null;
    }

    /**
     * Validate if amount is within merchant's allowed range.
     */
    public function isAmountValid(float $amount): bool
    {
        if ($this->min_amount !== null && $amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $amount > (float) $this->max_amount) {
            return false;
        }

        return true;
    }
}
