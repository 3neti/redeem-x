<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Bavix\Wallet\Interfaces\Confirmable;
use Bavix\Wallet\Interfaces\Customer;
use Bavix\Wallet\Interfaces\Wallet;
use Bavix\Wallet\Traits\CanConfirm;
use Bavix\Wallet\Traits\CanPay;
use Bavix\Wallet\Traits\HasWalletFloat;
use FrittenKeeZ\Vouchers\Concerns\HasVouchers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Merchant\Traits\HasMerchant;
use LBHurtado\Merchant\Traits\HasVendorAlias;
use LBHurtado\ModelChannel\Traits\HasChannels;
use LBHurtado\PaymentGateway\Traits\HasTopUps;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Wallet\Traits\HasPlatformWallets;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Confirmable, Customer, Wallet
{
    use CanConfirm;

    use CanPay;
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    use HasChannels;
    use HasMerchant;

    /**
     * Exclude 'mobile' from HasChannels __get/__set interception.
     * Mobile is stored on the users.mobile column (canonical for auth).
     * The trait still handles webhook, telegram, whatsapp, viber.
     */
    protected array $excludedChannels = ['mobile'];
    use HasPlatformWallets;
    use HasRoles;
    use HasTopUps;
    use HasVendorAlias;
    use HasVouchers;
    use HasWalletFloat;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'mobile',
        'workos_id',
        'auth_source',
        'status',
        'last_login_at',
        'avatar',
        'ui_preferences',
        'bank_accounts',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'workos_id',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'mobile',
        'webhook',
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
            'mobile_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'ip_whitelist' => 'array',
            'ip_whitelist_enabled' => 'boolean',
            'rate_limit_tier' => 'string',
            'signature_secret' => 'string',
            'signature_enabled' => 'boolean',
            'ui_preferences' => 'array',
            'bank_accounts' => 'array',
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

    public function authIdentities(): HasMany
    {
        return $this->hasMany(AuthIdentity::class);
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
                $date->copy()->endOfMonth(),
            ]);
    }

    /**
     * Boot method for User model.
     * Syncs users.mobile column → channels table on save.
     */
    protected static function booted(): void
    {
        static::saved(function (User $user) {
            $mobile = $user->getAttribute('mobile');
            if (! $mobile) {
                return;
            }

            // Sync on create (wasRecentlyCreated) or update (wasChanged)
            if ($user->wasRecentlyCreated || $user->wasChanged('mobile')) {
                $user->setChannel('mobile', $mobile);
            }
        });
    }

    /**
     * Normalize mobile to E.164 on write.
     * This is the single enforcement point — all code paths go through it.
     */
    public function setMobileAttribute($value): void
    {
        if ($value) {
            try {
                // Auto-detect country from E.164 input, fall back to PH for national format
                $value = phone($value)->formatE164();
            } catch (\Throwable) {
                try {
                    $value = phone($value, 'PH')->formatE164();
                } catch (\Throwable) {
                    // Keep raw if unparseable
                }
            }
        }
        $this->attributes['mobile'] = $value;
    }

    /**
     * Get the raw E.164 mobile value from storage.
     * Reads column first, falls back to channels table.
     */
    public function getRawMobile(): ?string
    {
        $mobile = $this->attributes['mobile'] ?? null;

        if (! $mobile) {
            $channel = $this->relationLoaded('channels')
                ? $this->channels->firstWhere('name', 'mobile')
                : $this->channels()->where('name', 'mobile')->first();

            $mobile = $channel?->value;
        }

        return $mobile ?: null;
    }

    /**
     * Get the mobile value formatted for display.
     *
     * Format is configurable via config('app.phone_display_format').
     * Default: +63 (917) 301-1987
     */
    public function getMobileAttribute(): ?string
    {
        $mobile = $this->getRawMobile();

        if (! $mobile) {
            return null;
        }

        return \App\Support\PhoneFormatter::forDisplay($mobile);
    }

    /**
     * Get the webhook channel value.
     *
     * This accessor makes the magic property from HasChannels trait
     * available in JSON serialization (via $appends).
     */
    public function getWebhookAttribute(): ?string
    {
        // Get raw webhook value from channels
        $channel = $this->relationLoaded('channels')
            ? $this->channels->firstWhere('name', 'webhook')
            : $this->channels()->where('name', 'webhook')->first();

        return $channel?->value;
    }

    /**
     * Route notifications for the EngageSpark channel.
     * Returns raw E.164 format — SMS APIs need machine-readable numbers.
     */
    public function routeNotificationForEngageSpark(): ?string
    {
        return $this->getRawMobile();
    }

    /**
     * Get the mobile number in national format for QR code generation.
     *
     * The Omnipay gateway will add the alias prefix automatically,
     * so we only return the national mobile format here.
     *
     * Format: National mobile (09173011987)
     * Gateway adds: 91500 + 09173011987 = 9150009173011987
     */
    public function getAccountNumberAttribute(): ?string
    {
        $raw = $this->getRawMobile();

        if (! $raw) {
            return null;
        }

        // Payment gateway needs national format (09173011987)
        try {
            return phone($raw, 'PH')->formatForMobileDialingInCountry('PH');
        } catch (\Throwable) {
            return $raw;
        }
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
                'metadata',
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

    /**
     * Get all bank accounts for this user.
     *
     * @return array Array of bank account objects
     */
    public function getBankAccounts(): array
    {
        return $this->bank_accounts ?? [];
    }

    /**
     * Add a bank account for this user.
     *
     * @param  string  $bankCode  Bank code (e.g., 'GXCHPHM2XXX', 'BOPIPHMM')
     * @param  string  $accountNumber  Account number or mobile number
     * @param  string|null  $label  Optional label (e.g., 'Primary GCash', 'BPI Savings')
     * @param  bool  $isDefault  Whether this should be the default account
     * @return array The newly created bank account
     */
    public function addBankAccount(string $bankCode, string $accountNumber, ?string $label = null, bool $isDefault = false): array
    {
        $accounts = $this->getBankAccounts();

        // If this is the first account or marked as default, make it default
        if ($isDefault || empty($accounts)) {
            // Unset default flag from all existing accounts
            foreach ($accounts as &$account) {
                $account['is_default'] = false;
            }
        }

        $newAccount = [
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'bank_code' => $bankCode,
            'account_number' => $accountNumber,
            'label' => $label,
            'is_default' => $isDefault || empty($accounts),
            'created_at' => now()->toIso8601String(),
        ];

        $accounts[] = $newAccount;
        $this->bank_accounts = $accounts;
        $this->save();

        return $newAccount;
    }

    /**
     * Remove a bank account by ID.
     *
     * @param  string  $id  Bank account UUID
     * @return bool Whether the account was removed
     */
    public function removeBankAccount(string $id): bool
    {
        $accounts = $this->getBankAccounts();
        $filtered = array_filter($accounts, fn ($account) => $account['id'] !== $id);

        if (count($filtered) === count($accounts)) {
            return false; // Account not found
        }

        $this->bank_accounts = array_values($filtered);
        $this->save();

        return true;
    }

    /**
     * Get the default bank account.
     *
     * @return array|null Default bank account or null if none set
     */
    public function getDefaultBankAccount(): ?array
    {
        $accounts = $this->getBankAccounts();

        foreach ($accounts as $account) {
            if ($account['is_default'] ?? false) {
                return $account;
            }
        }

        // If no default set but accounts exist, return first one
        return ! empty($accounts) ? $accounts[0] : null;
    }

    /**
     * Set a bank account as default by ID.
     *
     * @param  string  $id  Bank account UUID
     * @return bool Whether the default was set
     */
    public function setDefaultBankAccount(string $id): bool
    {
        $accounts = $this->getBankAccounts();
        $found = false;

        foreach ($accounts as &$account) {
            if ($account['id'] === $id) {
                $account['is_default'] = true;
                $found = true;
            } else {
                $account['is_default'] = false;
            }
        }

        if (! $found) {
            return false;
        }

        $this->bank_accounts = $accounts;
        $this->save();

        return true;
    }

    /**
     * Get a bank account by ID.
     *
     * @param  string  $id  Bank account UUID
     * @return array|null Bank account or null if not found
     */
    public function getBankAccountById(string $id): ?array
    {
        $accounts = $this->getBankAccounts();

        foreach ($accounts as $account) {
            if ($account['id'] === $id) {
                return $account;
            }
        }

        return null;
    }
}
