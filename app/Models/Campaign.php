<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\WithData;

class Campaign extends Model
{
    use HasFactory, WithData;

    protected string $dataClass = VoucherInstructionsData::class;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'status',
        'instructions',
    ];

    protected $casts = [
        'instructions' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vouchers(): BelongsToMany
    {
        return $this->belongsToMany(Voucher::class, 'campaign_voucher')
            ->using(CampaignVoucher::class)
            ->withPivot('instructions_snapshot')
            ->withTimestamps();
    }

    public function campaignVouchers(): HasMany
    {
        return $this->hasMany(CampaignVoucher::class);
    }

    public function getInstructionsAttribute($value): VoucherInstructionsData
    {
        return VoucherInstructionsData::from(json_decode($value, true));
    }

    public function setInstructionsAttribute($value): void
    {
        $this->attributes['instructions'] = json_encode(
            $value instanceof VoucherInstructionsData ? $value->toArray() : $value
        );
    }

    protected static function booted(): void
    {
        static::creating(function ($campaign) {
            if (empty($campaign->slug)) {
                $campaign->slug = Str::slug($campaign->name.'-'.Str::random(6));
            }
        });
    }
}
