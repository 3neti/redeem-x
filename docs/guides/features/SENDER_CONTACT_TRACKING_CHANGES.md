# Sender Contact Tracking - Key Changes

## Updated: Store Institution Per-Transaction

### ❌ **Previous Approach** (Would Cause Data Loss)
- Store `institution_code` in `contacts` table
- **Problem**: Overwrites when sender uses different payment method
- **Example**: 
  - Day 1: Sender uses GCash → Store `GXCHPHM2XXX`
  - Day 2: Same sender uses Maya → **Overwrite to** `PMYAPHM2XXX`
  - **Lost**: History that they used GCash before

### ✅ **New Approach** (Preserves Full History)
- Store institution in `contact_user.metadata` JSON array
- Each transaction preserves its payment method
- **Example**:
  ```json
  {
    "metadata": [
      {
        "institution": "GXCHPHM2XXX",
        "operation_id": "262424818",
        "channel": "INSTAPAY",
        "timestamp": "2025-11-15T20:52:37"
      },
      {
        "institution": "PMYAPHM2XXX",
        "operation_id": "262500123",
        "channel": "INSTAPAY",
        "timestamp": "2025-11-16T10:15:22"
      }
    ]
  }
  ```

---

## Database Schema Changes

### ❌ Removed Migration
- **DO NOT CREATE**: `add_institution_code_to_contacts_table`
- Reason: Institution code not stored on Contact model

### ✅ Single Migration
- **CREATE**: `create_contact_user_table`
- Institution stored in `metadata` JSON field (per-transaction)

---

## Model Method Changes

### Contact Model

#### ❌ Removed
```php
public function getInstitutionNameAttribute(): string
{
    return match($this->institution_code) {
        'GXCHPHM2XXX' => 'GCash',
        // ...
    };
}
```

#### ✅ Added
```php
// Get all institutions this contact has used for a specific user
public function institutionsUsed(User $user): array

// Get most recent institution used
public function latestInstitution(User $user): ?string

// Static helper for institution name lookup
public static function institutionName(string $code): string
```

---

## Usage Examples

### Before (Would Lose History)
```php
$contact = Contact::find(1);
echo $contact->institution_name; // Only shows latest
```

### After (Full History)
```php
$contact = Contact::find(1);
$user = User::find(1);

// Get all institutions ever used
$institutions = $contact->institutionsUsed($user);
// ['GXCHPHM2XXX', 'PMYAPHM2XXX', 'BOPIPHM2XXX']

// Get most recent
$latest = $contact->latestInstitution($user);
echo Contact::institutionName($latest); // "Maya"

// Get transaction history with institutions
$pivot = $user->senders()->find($contact->id)->pivot;
foreach ($pivot->metadata as $tx) {
    echo "{$tx['timestamp']}: via " . Contact::institutionName($tx['institution']);
}
```

---

## Benefits of New Approach

1. **No Data Loss**: Every transaction's payment method preserved
2. **Switching Detection**: Know when someone changes from GCash to Maya
3. **Analytics**: "45% of transactions via GCash, 30% via Maya"
4. **Flexibility**: Can query institution history without complex joins
5. **Audit Trail**: Complete record of payment methods used

---

## Migration Path

### If You Already Implemented Old Approach

1. **Extract institution codes** from existing contact records to metadata:
```php
// Migration to convert
DB::table('contact_user')->get()->each(function ($pivot) {
    $contact = Contact::find($pivot->contact_id);
    if ($contact && $contact->institution_code) {
        $metadata = json_decode($pivot->metadata, true) ?? [];
        
        // Add institution to each transaction in metadata
        $metadata = array_map(function ($tx) use ($contact) {
            $tx['institution'] = $contact->institution_code;
            return $tx;
        }, $metadata);
        
        DB::table('contact_user')
            ->where('id', $pivot->id)
            ->update(['metadata' => json_encode($metadata)]);
    }
});
```

2. **Drop institution_code** column:
```php
Schema::table('contacts', function (Blueprint $table) {
    $table->dropColumn('institution_code');
});
```

---

## Testing Updates

### New Test Added
```php
it('tracks institution per transaction in metadata', function () {
    $user = User::factory()->create();
    $sender = Contact::fromWebhookSender([...]);
    
    // First deposit via GCash
    $user->recordDepositFrom($sender, 55.00, ['institution' => 'GXCHPHM2XXX']);
    
    // Second deposit via Maya
    $user->recordDepositFrom($sender, 100.00, ['institution' => 'PMYAPHM2XXX']);
    
    // Verify both institutions tracked
    $institutions = $sender->institutionsUsed($user);
    expect($institutions)->toContain('GXCHPHM2XXX', 'PMYAPHM2XXX');
});
```

---

## Summary

**Changed**: Institution code storage from contact-level to transaction-level
**Reason**: Prevent data loss when senders use multiple payment methods
**Impact**: Better analytics, full history, no overwrites
**Migration**: Only one migration needed (contact_user pivot table)
