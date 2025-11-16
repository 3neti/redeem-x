<?php

namespace LBHurtado\Contact\Models;

use LBHurtado\Contact\Traits\{HasAdditionalAttributes, HasMeta};
use LBHurtado\Contact\Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LBHurtado\Contact\Traits\HasBankAccount;
use LBHurtado\Contact\Contracts\Bankable;
use LBHurtado\Contact\Traits\HasMobile;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Contact.
 *
 * @property int         $id
 * @property string      $mobile
 * @property string      $country
 * @property string      $bank_account
 * @property string      $bank_code
 * @property string      $account_number
 * @property string      $name
 *
 * @method int getKey()
 */
class Contact extends Model implements Bankable
{
    use HasAdditionalAttributes;
    use HasBankAccount;
    use HasFactory;
    use HasMobile;
    use HasMeta;

    protected $fillable = [
        'mobile',
        'country',
        'bank_account',
    ];

    protected $appends = [
        'name'
    ];

    public static function booted(): void
    {
        static::creating(function (Contact $contact) {
            $contact->country = $contact->country
                ?: config('contact.default.country');
            // ensure there's always a bank_account like "BANK_CODE:ACCOUNT_NUMBER"
            // Only generate a bank_account if one wasn't explicitly set
            if (empty($contact->bank_account)) {
                $defaultCode = config('contact.default.bank_code');
                $contact->bank_account = "{$defaultCode}:{$contact->mobile}";
            }
//            $contact->bank_account = ($contact->bank_account
//                    ?: config('contact.default.bank_code'))
//                . ':' . $contact->mobile;
        });
    }

    public static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }

    public function getBankCodeAttribute(): string
    {
        return $this->getBankCode();
    }

    public function getAccountNumberAttribute(): string
    {
        return $this->getAccountNumber();
    }
    
    /**
     * Users who received payments from this contact (as sender)
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(
            config('payment-gateway.models.user'),
            'contact_user'
        )->withPivot([
            'relationship_type',
            'total_sent',
            'transaction_count',
            'first_transaction_at',
            'last_transaction_at',
            'metadata'
        ])->withTimestamps();
    }
    
    /**
     * Get all institutions this contact has used to send to a specific user
     */
    public function institutionsUsed($user): array
    {
        $pivot = $this->recipients()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;
        
        if (!$pivot || !$pivot->metadata) {
            return [];
        }
        
        // Decode if it's a JSON string
        $metadata = is_string($pivot->metadata) 
            ? json_decode($pivot->metadata, true) 
            : $pivot->metadata;
        
        return collect($metadata)
            ->pluck('institution')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
    }
    
    /**
     * Get the most recent institution used by this contact for a specific user
     */
    public function latestInstitution($user): ?string
    {
        $pivot = $this->recipients()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;
        
        if (!$pivot || !$pivot->metadata) {
            return null;
        }
        
        // Decode if it's a JSON string
        $metadata = is_string($pivot->metadata) 
            ? json_decode($pivot->metadata, true) 
            : $pivot->metadata;
        
        return collect($metadata)->last()['institution'] ?? null;
    }
    
    /**
     * Get institution display name from code
     */
    public static function institutionName(string $code): string
    {
        return match($code) {
            'GXCHPHM2XXX' => 'GCash',
            'PMYAPHM2XXX' => 'Maya',
            'BOPIPHM2XXX' => 'BPI',
            'BDONPHM2XXX' => 'BDO',
            'MBTCPHM2XXX' => 'Metrobank',
            'UBPHPHM2XXX' => 'UnionBank',
            default => $code,
        };
    }
    
    /**
     * Find or create contact from webhook sender data
     * 
     * Note: Institution code is NOT stored here - it's stored per-transaction
     * in the pivot table metadata to preserve full payment method history.
     */
    public static function fromWebhookSender(array $senderData): self
    {
        // Normalize mobile to E.164 format
        $mobile = $senderData['accountNumber'];
        if (str_starts_with($mobile, '0')) {
            $mobile = '63' . substr($mobile, 1);
        }
        
        return static::updateOrCreate(
            ['mobile' => $mobile],
            [
                // Institution code stored in pivot metadata, not here
            ]
        );
    }
}
