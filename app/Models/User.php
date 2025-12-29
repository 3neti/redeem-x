<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Bavix\Wallet\Interfaces\{Customer, Wallet};
use Bavix\Wallet\Traits\{CanPay, HasWalletFloat};
use FrittenKeeZ\Vouchers\Concerns\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};
use Illuminate\Foundation\Auth\User as Authenticatable;
use LBHurtado\Contact\Models\Contact;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\PaymentGateway\Traits\{HasMerchant, HasTopUps};
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Wallet, Customer
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;
    use HasPlatformWallets;
    use HasWalletFloat;
    use CanPay;
    use HasVouchers;
    use HasChannels;
    use HasMerchant;
    use HasTopUps;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'workos_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'workos_id',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'ip_whitelist' => 'array',
            'ip_whitelist_enabled' => 'boolean',
            'rate_limit_tier' => 'string',
        ];
    }

    /**
     * Create an API token with specific abilities.
     *
     * @param  string  $name  Token name (e.g., 'mobile-app', 'third-party-integration')
     * @param  array  $abilities  Token abilities/permissions
     * @return \Laravel\Sanctum\NewAccessToken
     */
    public function createApiToken(string $name, array $abilities = ['*'])
    {
        return $this->createToken($name, $abilities);
    }

    /**
     * Get available API token abilities.
     *
     * @return array
     */
    public static function getApiTokenAbilities(): array
    {
        return [
            'voucher:generate',
            'voucher:list',
            'voucher:view',
            'voucher:cancel',
            'transaction:list',
            'transaction:view',
            'transaction:export',
            'settings:view',
            'settings:update',
            'contact:list',
            'contact:view',
        ];
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function topUps(): HasMany
    {
        return $this->hasMany(TopUp::class);
    }

    public function generatedVouchers()
    {
        return $this->belongsToMany(
            Voucher::class,
            'user_voucher',
            'user_id',
            'voucher_code',
            'id',
            'code'
        )->withTimestamps();
    }
    
    public function voucherGenerationCharges(): HasMany
    {
        return $this->hasMany(VoucherGenerationCharge::class);
    }
    
    public function monthlyCharges(?int $year = null, ?int $month = null)
    {
        $year = $year ?? now()->year;
        $month = $month ?? now()->month;
        $date = \Carbon\Carbon::create($year, $month, 1);
        
        return $this->voucherGenerationCharges()
            ->whereBetween('generated_at', [
                $date->copy()->startOfMonth(),
                $date->copy()->endOfMonth()
            ]);
    }
    
    /**
     * Get the mobile number in national format for QR code generation.
     * 
     * The Omnipay gateway will add the alias prefix automatically,
     * so we only return the national mobile format here.
     * 
     * Format: National mobile (09173011987)
     * Gateway adds: 91500 + 09173011987 = 9150009173011987
     * 
     * @return string|null
     */
    public function getAccountNumberAttribute(): ?string
    {
        $mobile = $this->mobile;
        
        if (!$mobile) {
            return null;
        }
        
        // Mobile is stored in E.164 format without + (e.g., 639173011987)
        // Convert to national format (09173011987)
        if (str_starts_with($mobile, '63') && strlen($mobile) === 12) {
            return '0' . substr($mobile, 2);
        }
        
        // If already in national format or other format, return as-is
        return $mobile;
    }
    
    /**
     * Contacts who sent money to this user
     */
    public function senders(): BelongsToMany
    {
        return $this->belongsToMany(
            Contact::class,
            'contact_user'
        )->wherePivot('relationship_type', 'sender')
         ->withPivot([
             'total_sent',
             'transaction_count',
             'first_transaction_at',
             'last_transaction_at',
             'metadata'
         ])->withTimestamps();
    }
    
    /**
     * Record a deposit from a sender
     */
    public function recordDepositFrom(Contact $sender, float $amount, array $metadata = []): void
    {
        $existing = $this->senders()
            ->where('contact_id', $sender->id)
            ->first();
        
        if ($existing) {
            // Update existing sender relationship
            $existingMetadata = is_string($existing->pivot->metadata) 
                ? json_decode($existing->pivot->metadata, true) 
                : ($existing->pivot->metadata ?? []);
            
            $this->senders()->updateExistingPivot($sender->id, [
                'total_sent' => $existing->pivot->total_sent + $amount,
                'transaction_count' => $existing->pivot->transaction_count + 1,
                'last_transaction_at' => now(),
                'metadata' => json_encode(array_merge(
                    $existingMetadata,
                    [$metadata] // Append new metadata
                )),
            ]);
        } else {
            // Create new sender relationship
            $this->senders()->attach($sender->id, [
                'relationship_type' => 'sender',
                'total_sent' => $amount,
                'transaction_count' => 1,
                'first_transaction_at' => now(),
                'last_transaction_at' => now(),
                'metadata' => json_encode([$metadata]),
            ]);
        }
    }
}
