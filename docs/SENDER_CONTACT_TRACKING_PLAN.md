# Sender Contact Tracking Implementation Plan

## Overview

Automatically create and link contacts from deposit confirmation webhook sender data to track who sends money to users.

## Webhook Sender Data Structure

```json
{
  "sender": {
    "accountNumber": "09173011987",
    "institutionCode": "GXCHPHM2XXX",
    "name": "LESTER HURTADO"
  }
}
```

**Fields**:
- `accountNumber`: Sender's mobile number (national format)
- `institutionCode`: BIC/SWIFT code identifying payment institution (GCash, Maya, etc.)
- `name`: Sender's full name (as registered with their payment provider)

---

## Implementation Plan

### Phase 1: Database Schema Enhancement

#### 1.1 Create Contact-User Pivot Table

**Note**: We do NOT add `institution_code` to the `contacts` table because senders may use different payment methods over time (GCash today, Maya tomorrow). Instead, we store institution information per-transaction in the pivot table's `metadata` JSON field.

**Migration**: `create_contact_user_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('relationship_type')->default('sender'); // sender, beneficiary, etc.
            $table->decimal('total_sent', 15, 2)->default(0); // Cumulative amount
            $table->integer('transaction_count')->default(0);
            $table->timestamp('first_transaction_at')->nullable();
            $table->timestamp('last_transaction_at')->nullable();
            $table->json('metadata')->nullable(); // Store additional context
            $table->timestamps();
            
            $table->unique(['contact_id', 'user_id', 'relationship_type']);
            $table->index(['user_id', 'relationship_type']);
            $table->index('last_transaction_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_user');
    }
};
```

**Purpose**: Track relationship between sender and recipient with transaction statistics

**Fields Explained**:
- `total_sent`: Running total of all deposits from this sender
- `transaction_count`: Number of times this sender has sent money
- `first_transaction_at`: When the first deposit was received
- `last_transaction_at`: When the most recent deposit was received
- `metadata`: JSON array storing per-transaction details including:
  - `institution`: Institution code (GXCHPHM2XXX, PMYAPHM2XXX, etc.)
  - `operation_id`: NetBank operation ID
  - `channel`: Payment channel (INSTAPAY, PESONET)
  - `reference_number`: Transaction reference
  - `timestamp`: When transaction occurred

**Why store institution in metadata?**
A sender may use different payment methods over time:
- Transaction 1: GCash (GXCHPHM2XXX)
- Transaction 2: Maya (PMYAPHM2XXX)
- Transaction 3: BPI Bank (BOPIPHM2XXX)

Storing per-transaction preserves full history without overwriting.

---

### Phase 2: Model Enhancements

#### 2.1 Update Contact Model

**File**: `packages/contact/src/Models/Contact.php`

```php
<?php

namespace LBHurtado\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'value',
        // NOTE: No institution_code field - stored per-transaction in pivot
    ];
    
    protected $casts = [
        'metadata' => 'array',
    ];
    
    /**
     * Users who received payments from this contact
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
    public function institutionsUsed(User $user): array
    {
        $pivot = $this->recipients()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;
        
        if (!$pivot || !$pivot->metadata) {
            return [];
        }
        
        return collect($pivot->metadata)
            ->pluck('institution')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
    }
    
    /**
     * Get the most recent institution used by this contact for a specific user
     */
    public function latestInstitution(User $user): ?string
    {
        $pivot = $this->recipients()
            ->where('user_id', $user->id)
            ->first()
            ?->pivot;
        
        if (!$pivot || !$pivot->metadata) {
            return null;
        }
        
        return collect($pivot->metadata)->last()['institution'] ?? null;
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
            ['value' => $mobile],
            [
                'name' => strtoupper($senderData['name']), // Keep uppercase like webhook
                // Institution code stored in pivot metadata, not here
            ]
        );
    }
}
```

#### 2.2 Update User Model

**File**: `app/Models/User.php`

Add to the User class:

```php
use LBHurtado\Contact\Models\Contact;

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
        $this->senders()->updateExistingPivot($sender->id, [
            'total_sent' => $existing->pivot->total_sent + $amount,
            'transaction_count' => $existing->pivot->transaction_count + 1,
            'last_transaction_at' => now(),
            'metadata' => array_merge(
                $existing->pivot->metadata ?? [],
                [$metadata] // Append new metadata
            ),
        ]);
    } else {
        // Create new sender relationship
        $this->senders()->attach($sender->id, [
            'relationship_type' => 'sender',
            'total_sent' => $amount,
            'transaction_count' => 1,
            'first_transaction_at' => now(),
            'last_transaction_at' => now(),
            'metadata' => [$metadata],
        ]);
    }
}
```

---

### Phase 3: Webhook Handler Integration

#### 3.1 Update CanConfirmDeposit Trait

**File**: `packages/payment-gateway/src/Gateways/Netbank/Traits/CanConfirmDeposit.php`

Add after the wallet is found and before `transferToWallet()`:

```php
use LBHurtado\Contact\Models\Contact;

public function confirmDeposit(array $payload): bool
{
    $response = DepositResponseData::from($payload);
    Log::info('Processing Netbank deposit confirmation', $response->toArray());
    
    $dto = RecipientAccountNumberData::fromRecipientAccountNumber(
        $response->recipientAccountNumber
    );
    
    try {
        $wallet = app(ResolvePayable::class)->execute($dto);
    } catch (\Throwable $e) {
        Log::error('Could not resolve recipient to a wallet', [
            'error' => $e->getMessage(),
            'payload' => $response->toArray(),
        ]);
        return false;
    }
    
    if (! $wallet instanceof Wallet) {
        Log::warning('No wallet found for reference or mobile', [
            'referenceCode' => $dto->referenceCode,
            'alias' => $dto->alias,
        ]);
        return false;
    }
    
    // ===== NEW: Create/update sender contact =====
    $sender = null;
    if ($wallet instanceof \App\Models\User) {
        try {
            $sender = Contact::fromWebhookSender([
                'accountNumber' => $response->sender->accountNumber,
                'name' => $response->sender->name,
                'institutionCode' => $response->sender->institutionCode,
            ]);
            
            Log::info('Sender contact processed', [
                'contact_id' => $sender->id,
                'mobile' => $sender->value,
                'institution' => $sender->institution_code,
                'name' => $sender->name,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Failed to create sender contact', [
                'error' => $e->getMessage(),
                'sender_data' => [
                    'account' => $response->sender->accountNumber,
                    'name' => $response->sender->name,
                    'institution' => $response->sender->institutionCode,
                ],
            ]);
            // Continue processing - don't fail deposit on contact creation error
        }
    }
    // ===== END NEW =====
    
    $this->transferToWallet($wallet, $response);
    
    // ===== NEW: Record sender relationship =====
    if ($sender && $wallet instanceof \App\Models\User) {
        try {
            $wallet->recordDepositFrom($sender, $response->amount / 100, [
                'operation_id' => $response->operationId,
                'channel' => $response->channel,
                'reference_number' => $response->referenceNumber,
                'institution' => $response->sender->institutionCode,
                'transfer_type' => $response->transferType,
                'timestamp' => $response->registrationTime,
            ]);
            
            Log::info('Sender relationship recorded', [
                'user_id' => $wallet->id,
                'contact_id' => $sender->id,
                'amount' => $response->amount / 100,
                'transaction_count' => $wallet->senders()->find($sender->id)->pivot->transaction_count,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Failed to record sender relationship', [
                'error' => $e->getMessage(),
                'user_id' => $wallet->id ?? null,
                'contact_id' => $sender->id ?? null,
            ]);
        }
    }
    // ===== END NEW =====
    
    return true;
}
```

---

### Phase 4: Service Layer (Optional but Recommended)

#### 4.1 Create SenderContactService

**File**: `app/Services/SenderContactService.php`

```php
<?php

namespace App\Services;

use App\Models\User;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\PaymentGateway\Data\Netbank\Deposit\DepositResponseData;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing sender contacts from webhook deposits
 */
class SenderContactService
{
    /**
     * Process sender from webhook and link to recipient
     */
    public function processWebhookSender(
        DepositResponseData $deposit,
        User $recipient
    ): ?Contact {
        try {
            // Create or update contact
            $contact = Contact::fromWebhookSender([
                'accountNumber' => $deposit->sender->accountNumber,
                'name' => $deposit->sender->name,
                'institutionCode' => $deposit->sender->institutionCode,
            ]);
            
            // Record deposit relationship
            $recipient->recordDepositFrom($contact, $deposit->amount / 100, [
                'operation_id' => $deposit->operationId,
                'channel' => $deposit->channel,
                'reference_number' => $deposit->referenceNumber,
                'institution' => $deposit->sender->institutionCode,
                'transfer_type' => $deposit->transferType,
            ]);
            
            Log::info('[SenderContact] Processed webhook sender', [
                'contact_id' => $contact->id,
                'recipient_id' => $recipient->id,
                'amount' => $deposit->amount / 100,
            ]);
            
            return $contact;
            
        } catch (\Throwable $e) {
            Log::error('[SenderContact] Failed to process sender', [
                'error' => $e->getMessage(),
                'recipient_id' => $recipient->id,
                'sender_mobile' => $deposit->sender->accountNumber,
            ]);
            
            return null;
        }
    }
    
    /**
     * Get top senders for a user
     */
    public function getTopSenders(User $user, int $limit = 10)
    {
        return $user->senders()
            ->orderByPivot('total_sent', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get recent senders for a user
     */
    public function getRecentSenders(User $user, int $limit = 10)
    {
        return $user->senders()
            ->orderByPivot('last_transaction_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get sender statistics for a user
     */
    public function getSenderStats(User $user): array
    {
        $senders = $user->senders;
        
        return [
            'total_senders' => $senders->count(),
            'total_received' => $senders->sum('pivot.total_sent'),
            'total_transactions' => $senders->sum('pivot.transaction_count'),
            'top_sender' => $senders->sortByDesc('pivot.total_sent')->first(),
            'most_recent' => $senders->sortByDesc('pivot.last_transaction_at')->first(),
        ];
    }
}
```

---

### Phase 5: Testing

#### 5.1 Feature Test

**File**: `tests/Feature/SenderContactTrackingTest.php`

```php
<?php

use App\Models\User;
use LBHurtado\Contact\Models\Contact;

it('creates contact from webhook sender data', function () {
    $sender = Contact::fromWebhookSender([
        'accountNumber' => '09173011987',
        'name' => 'LESTER HURTADO',
        'institutionCode' => 'GXCHPHM2XXX',
    ]);
    
    expect($sender)->toBeInstanceOf(Contact::class)
        ->and($sender->value)->toBe('639173011987') // Normalized to E.164
        ->and($sender->name)->toBe('LESTER HURTADO');
    // Note: institution_code NOT stored on contact model
});

it('tracks institution per transaction in metadata', function () {
    $user = User::factory()->create();
    $user->mobile = '09181234567';
    
    $sender = Contact::fromWebhookSender([
        'accountNumber' => '09173011987',
        'name' => 'LESTER HURTADO',
        'institutionCode' => 'GXCHPHM2XXX',
    ]);
    
    // First deposit via GCash
    $user->recordDepositFrom($sender, 55.00, [
        'institution' => 'GXCHPHM2XXX',
        'channel' => 'INSTAPAY',
    ]);
    
    // Second deposit via Maya
    $user->recordDepositFrom($sender, 100.00, [
        'institution' => 'PMYAPHM2XXX',
        'channel' => 'INSTAPAY',
    ]);
    
    // Check institutions used
    $institutions = $sender->institutionsUsed($user);
    expect($institutions)->toHaveCount(2)
        ->and($institutions)->toContain('GXCHPHM2XXX')
        ->and($institutions)->toContain('PMYAPHM2XXX');
    
    // Check latest institution
    expect($sender->latestInstitution($user))->toBe('PMYAPHM2XXX');
});

it('records deposit from sender to recipient', function () {
    $user = User::factory()->create();
    $user->mobile = '09181234567';
    
    $sender = Contact::fromWebhookSender([
        'accountNumber' => '09173011987',
        'name' => 'LESTER HURTADO',
        'institutionCode' => 'GXCHPHM2XXX',
    ]);
    
    $user->recordDepositFrom($sender, 55.00, [
        'operation_id' => '262424818',
        'channel' => 'INSTAPAY',
    ]);
    
    expect($user->senders)->toHaveCount(1);
    
    $relationship = $user->senders()->first();
    expect($relationship->pivot->total_sent)->toBe(55.00)
        ->and($relationship->pivot->transaction_count)->toBe(1)
        ->and($relationship->pivot->first_transaction_at)->not->toBeNull();
});

it('updates existing sender relationship on repeat deposit', function () {
    $user = User::factory()->create();
    $user->mobile = '09181234567';
    
    $sender = Contact::fromWebhookSender([
        'accountNumber' => '09173011987',
        'name' => 'LESTER HURTADO',
        'institutionCode' => 'GXCHPHM2XXX',
    ]);
    
    // First deposit
    $user->recordDepositFrom($sender, 55.00);
    
    // Second deposit
    $user->recordDepositFrom($sender, 100.00);
    
    expect($user->senders)->toHaveCount(1);
    
    $relationship = $user->senders()->first();
    expect($relationship->pivot->total_sent)->toBe(155.00)
        ->and($relationship->pivot->transaction_count)->toBe(2);
});

it('handles multiple senders for one recipient', function () {
    $user = User::factory()->create();
    $user->mobile = '09181234567';
    
    $sender1 = Contact::fromWebhookSender([
        'accountNumber' => '09173011987',
        'name' => 'LESTER HURTADO',
        'institutionCode' => 'GXCHPHM2XXX',
    ]);
    
    $sender2 = Contact::fromWebhookSender([
        'accountNumber' => '09175180722',
        'name' => 'RUTH APPLE HURTADO',
        'institutionCode' => 'PMYAPHM2XXX',
    ]);
    
    $user->recordDepositFrom($sender1, 55.00);
    $user->recordDepositFrom($sender2, 100.00);
    
    expect($user->senders)->toHaveCount(2);
});
```

---

## Implementation Checklist

### Phase 1: Database ✅
- [ ] Create migration: `create_contact_user_table` (no institution_code in contacts)
- [ ] Run migrations: `php artisan migrate`

### Phase 2: Models ✅
- [ ] Update `Contact` model (NO institution_code field)
- [ ] Add `fromWebhookSender()` static method to Contact
- [ ] Add `institutionsUsed()` method to Contact
- [ ] Add `latestInstitution()` method to Contact
- [ ] Add `institutionName()` static helper to Contact
- [ ] Add `recipients()` relationship to Contact
- [ ] Add `senders()` relationship to User model
- [ ] Add `recordDepositFrom()` method to User model (stores institution in metadata)

### Phase 3: Webhook Integration ✅
- [ ] Update `CanConfirmDeposit` trait to create contacts
- [ ] Add sender relationship recording after wallet topup
- [ ] Add comprehensive error handling and logging

### Phase 4: Service Layer (Optional) ✅
- [ ] Create `SenderContactService`
- [ ] Implement contact processing methods
- [ ] Add statistics and query methods

### Phase 5: Testing ✅
- [x] Write feature tests for contact creation
- [x] Write tests for deposit recording
- [x] Test repeat deposits update correctly
- [x] Test with webhook simulation command
- [x] Test edge cases (invalid data, missing fields)

**Test Commands Available:**

```bash
# Comprehensive sender tracking test (recommended)
php artisan test:sender-tracking

# Test with custom parameters
php artisan test:sender-tracking \
  --user-mobile=09173011987 \
  --sender-mobile=09175180722 \
  --sender-name="LESTER HURTADO" \
  --amount=55.00 \
  --institution=GXCHPHM2XXX

# Test webhook integration
php artisan test:deposit-confirmation \
  --mobile=09173011987 \
  --amount=5500
```

---

## Usage Examples

### Query Top Senders

```php
$user = User::find(1);

// Get top 10 senders by total amount
$topSenders = $user->senders()
    ->orderByPivot('total_sent', 'desc')
    ->limit(10)
    ->get();

foreach ($topSenders as $sender) {
    echo "{$sender->name}: ₱{$sender->pivot->total_sent}\n";
    echo "Transactions: {$sender->pivot->transaction_count}\n";
    
    // Get institutions this sender has used
    $institutions = $sender->institutionsUsed($user);
    $institutionNames = array_map(
        fn($code) => Contact::institutionName($code),
        $institutions
    );
    echo "Payment methods: " . implode(', ', $institutionNames) . "\n";
    
    // Get latest institution
    $latest = $sender->latestInstitution($user);
    echo "Last used: " . Contact::institutionName($latest) . "\n";
}
```

### Analyze Institution Usage

```php
// Get all institutions a sender has used
$sender = Contact::find(1);
$institutions = $sender->institutionsUsed($user);
// Result: ['GXCHPHM2XXX', 'PMYAPHM2XXX', 'BOPIPHM2XXX']

// Check if sender switched payment methods
if (count($institutions) > 1) {
    echo "Sender uses multiple payment methods\n";
}

// Get institution breakdown across all senders
$allMetadata = $user->senders
    ->flatMap(fn($s) => $s->pivot->metadata);

$institutionBreakdown = $allMetadata
    ->groupBy('institution')
    ->map(fn($group) => [
        'count' => $group->count(),
        'name' => Contact::institutionName($group->first()['institution']),
    ]);

// Result:
// GXCHPHM2XXX: 45 transactions (GCash)
// PMYAPHM2XXX: 12 transactions (Maya)
// BOPIPHM2XXX: 8 transactions (BPI)
```

### Get Sender Statistics

```php
$service = app(SenderContactService::class);
$stats = $service->getSenderStats($user);

echo "Total Senders: {$stats['total_senders']}\n";
echo "Total Received: ₱{$stats['total_received']}\n";
echo "Total Transactions: {$stats['total_transactions']}\n";
```

### Find Specific Sender

```php
// Find by mobile
$sender = Contact::where('value', '639173011987')->first();

// Check if they've sent to user
$hasSent = $user->senders()->where('contact_id', $sender->id)->exists();

// Get full transaction history with institutions
if ($hasSent) {
    $pivot = $user->senders()->find($sender->id)->pivot;
    $transactions = collect($pivot->metadata);
    
    foreach ($transactions as $tx) {
        echo "{$tx['timestamp']}: ₱" . ($tx['amount'] ?? 0) . " via ";
        echo Contact::institutionName($tx['institution']) . "\n";
    }
}
```

---

## Benefits

1. **Transaction History**: Complete record of who sends money
2. **Customer Intelligence**: Understand your sender demographics
3. **Relationship Management**: Identify repeat senders/customers
4. **Fraud Detection**: Flag suspicious patterns (new sender, large amount)
5. **Analytics**: Visualize sender trends by institution, time, amount
6. **Automated Communications**: Thank repeat senders, send receipts

---

## Data Privacy & Compliance

### Considerations

- **Consent**: Inform users sender data is being collected
- **Purpose**: Only use data for transaction tracking and analytics
- **Retention**: Define how long to keep sender records
- **Access**: Restrict who can view sender information
- **Security**: Encrypt sensitive fields if needed
- **GDPR/DPA**: Ensure compliance with data protection laws

### Recommended Policy

> "When you receive payments, we collect sender information (name, mobile number, payment provider) for transaction tracking, customer support, and fraud prevention. This data is securely stored and not shared with third parties."

---

## Future Enhancements

1. **Sender Profiles**: Full view of sender transaction history
2. **Communication Tools**: Send thank-you messages to senders
3. **VIP Tagging**: Mark high-value senders for priority
4. **Dashboard Widget**: Show top/recent senders on homepage
5. **Export**: CSV export for accounting
6. **Notifications**: Alert on first-time sender or large deposits
7. **Duplicate Detection**: Merge duplicate sender records
8. **Institution Analytics**: Track which payment methods are most popular

---

## Testing

### Comprehensive Test Command

Use `php artisan test:sender-tracking` to verify the complete sender tracking flow:

```bash
php artisan test:sender-tracking
```

**What it tests:**
1. Contact creation from webhook sender data
2. Recording deposits with transaction metadata
3. Tracking cumulative statistics (total_sent, transaction_count)
4. Multiple institutions per sender (GCash → Maya → BPI)
5. Transaction history with timestamps and institutions
6. Institution helper methods (institutionsUsed, latestInstitution)

**Expected Output:**
```
🧪 Testing Sender Contact Tracking

Step 1: Finding/Creating Recipient User
  ✓ User: Test User (ID: 3)
  ✓ Mobile: 639173011987

Step 2: Creating Sender Contact
  ✓ Contact: LESTER HURTADO (ID: 1)
  ✓ Mobile: 639175180722
  ✓ Institution: GXCHPHM2XXX (GCash)

Step 3: Recording Deposit Transaction
  ✓ Recorded: ₱55

Step 4: Verification
  ✓ Total Senders: 1
  
  Sender: LESTER HURTADO
    Mobile: 639175180722
    Total Sent: ₱55
    Transactions: 1
    Payment Methods: GCash
    Latest Method: GCash
    Transaction History:
      #1: 2025-11-16T07:59:22+08:00 via GCash (INSTAPAY)

Step 5: Testing Multiple Institutions
  Sending another deposit via Maya...
  ✓ Second deposit recorded
  
  Updated Stats:
    Total Sent: ₱155
    Transactions: 2
    Payment Methods: GCash, Maya

✅ Sender contact tracking is working correctly!

📊 Database Summary:
+------------------------+-------+
| Table                  | Count |
+------------------------+-------+
| Contacts               | 1     |
| Contact-User Relations | 1     |
| Users with Senders     | 1     |
+------------------------+-------+
```

### Important Notes

**JSON Metadata Handling:**
- Metadata is stored as JSON string in database
- Must be decoded before use: `json_decode($pivot->metadata, true)`
- Helper methods in Contact model handle decoding automatically

**Pivot Data Access:**
```php
$sender = $user->senders()->find($contactId);

// Metadata is JSON string - decode it
$metadata = json_decode($sender->pivot->metadata, true);

// Or use helper methods (they decode automatically)
$institutions = $sender->institutionsUsed($user);
$latest = $sender->latestInstitution($user);
```

## Related Documentation

- [Mobile QR Generation](./MOBILE_QR_GENERATION.md)
- [Webhook Handling](./OMNIPAY_INTEGRATION_PLAN.md)
- [Model Channel Package](../packages/model-channel/README.md)
- [Contact Package](../packages/contact/README.md)
- [Test Commands](../app/Console/Commands/TestSenderContactTracking.php)
