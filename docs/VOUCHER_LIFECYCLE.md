# Voucher Lifecycle

This document explains the complete lifecycle of a voucher from generation to redemption, including important timing considerations.

## Voucher States

A voucher progresses through several states during its lifecycle:

### 1. **Created** (Immediately after generation)
- **Database record exists**: Yes
- **Ready for redemption**: ❌ No
- **Has cash entity**: ❌ No
- **`processed` flag**: `false`
- **`processed_on` timestamp**: `null`

When a voucher is first generated, it's inserted into the database but is **not yet ready for redemption**. The post-generation pipeline must complete before the voucher becomes redeemable.

**User experience**: If a user attempts redemption during this state, they'll see:
> "This voucher is still being prepared. Please wait a moment and try again."

### 2. **Processing** (Post-generation pipeline running)
- **Database record exists**: Yes
- **Ready for redemption**: ❌ No (still being prepared)
- **Has cash entity**: ⏳ In progress
- **`processed` flag**: `false`
- **`processed_on` timestamp**: `null`

The post-generation pipeline runs asynchronously via queue and performs:
1. Structure validation
2. Metadata normalization
3. Fraud checks
4. Usage limits
5. **Cash entity creation** ← Critical step
6. Audit logging
7. **Mark as processed** ← Final step

**Duration**: Typically 2-5 seconds for single voucher, up to 30+ seconds for bulk batches.

### 3. **Processed** (Ready for redemption)
- **Database record exists**: Yes
- **Ready for redemption**: ✅ Yes
- **Has cash entity**: ✅ Yes
- **`processed` flag**: `true`
- **`processed_on` timestamp**: Set to current time

The voucher is now fully prepared and can be redeemed. All prerequisite resources (cash entity, validations, etc.) are in place.

### 4. **Redeemed** (Successfully redeemed by user)
- **Database record exists**: Yes
- **Ready for redemption**: ❌ No (already redeemed)
- **Has cash entity**: Yes (may be withdrawn)
- **`redeemed_at` timestamp**: Set to redemption time
- **Post-redemption pipeline**: Runs automatically

Post-redemption actions:
1. Validate redeemer and cash
2. Persist inputs to voucher metadata
3. Disburse cash (if enabled)
4. Send notifications (email, SMS, webhook)

### 5. **Expired** (Past expiration date)
- **Database record exists**: Yes
- **Ready for redemption**: ❌ No
- **`expires_at` timestamp**: In the past

Expired vouchers cannot be redeemed, regardless of processing state.

## Why Vouchers May Not Be Immediately Redeemable

### The Race Condition Problem

Prior to v2.x, vouchers were immediately redeemable after database insertion, but the cash entity was created asynchronously. This caused a race condition:

```
Timeline (OLD BEHAVIOR - BUGGY):
T+0s:  Voucher inserted into database ✓
T+0s:  User can start redemption flow ⚠️
T+1s:  Post-generation pipeline queued
T+3s:  User completes redemption flow
T+3s:  ProcessRedemption runs
T+3s:  ValidateRedeemerAndCash: No cash entity! ❌
T+3s:  DisburseCash skipped (no disbursement) ❌
T+5s:  Post-generation pipeline finally creates cash entity (too late!)
```

**Result**: Voucher marked as redeemed but no money disbursed.

### The Solution: Processed Flag

Starting in v2.x, vouchers require the `processed` flag to be true before redemption:

```
Timeline (NEW BEHAVIOR - FIXED):
T+0s:  Voucher inserted into database ✓
T+0s:  User starts redemption flow
T+1s:  Post-generation pipeline queued
T+3s:  User completes redemption flow
T+3s:  ProcessRedemption checks processed flag
T+3s:  processed = false → throw VoucherNotProcessedException ⚠️
T+3s:  User sees "Voucher being prepared, retry in 3s"
T+5s:  Post-generation pipeline creates cash entity ✓
T+5s:  Voucher marked as processed ✓
T+8s:  User retries redemption
T+8s:  processed = true → redemption succeeds ✅
T+8s:  DisburseCash executes successfully ✅
```

**Result**: Voucher redeemed with successful disbursement.

## Technical Implementation

### Database Schema

The `processed` flag is a virtual attribute on the `Voucher` model:

```php
// packages/voucher/src/Models/Voucher.php
public function setProcessedAttribute(bool $value): self
{
    $this->setAttribute('processed_on', $value ? now() : null);
    return $this;
}

public function getProcessedAttribute(): bool
{
    return $this->getAttribute('processed_on')
        && $this->getAttribute('processed_on') <= now();
}
```

- **Storage**: `processed_on` column (datetime, nullable)
- **Access**: `$voucher->processed` (boolean)

### Redemption Flow

**Step 1: User initiates redemption**
```php
// app/Actions/Voucher/ProcessRedemption.php
public function handle(Voucher $voucher, PhoneNumber $phoneNumber, ...): bool
{
    // Check if voucher has been processed
    if (!$voucher->processed) {
        throw new VoucherNotProcessedException(
            'This voucher is still being prepared. Please wait a moment and try again.'
        );
    }
    
    // Continue with redemption...
}
```

**Step 2: Exception handling**
```php
// app/Http/Controllers/Redeem/RedeemController.php
public function confirm(Voucher $voucher): RedirectResponse
{
    try {
        ProcessRedemption::run($voucher, $phoneNumber, $inputs, $bankAccount);
        return redirect()->route('redeem.success', $voucher);
    } catch (VoucherNotProcessedException $e) {
        return redirect()
            ->route('redeem.finalize', $voucher)
            ->with('voucher_processing', true)
            ->with('error', $e->getMessage());
    }
}
```

**Step 3: Frontend retry UI**
```vue
<!-- resources/js/pages/redeem/Finalize.vue -->
<Alert v-if="voucher_processing" variant="default" class="border-blue-200 bg-blue-50">
    <Clock class="h-4 w-4 text-blue-600" />
    <AlertDescription class="text-blue-800">
        {{ error }}
        <div v-if="retryCountdown > 0" class="mt-2 font-semibold">
            Retry in {{ retryCountdown }} second{{ retryCountdown !== 1 ? 's' : '' }}...
        </div>
        <Button 
            v-if="retryCountdown === 0" 
            @click="handleConfirm" 
            size="sm" 
            class="mt-3"
        >
            Retry Now
        </Button>
    </AlertDescription>
</Alert>
```

### Post-Generation Pipeline

Configured in `config/voucher-pipeline.php`:

```php
'post-generation' => [
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\ValidateStructure::class,
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\NormalizeMetadata::class,
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\RunFraudChecks::class,
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\ApplyUsageLimits::class,
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\CreateCashEntities::class, // ← Cash created here
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\NotifyBatchCreator::class,
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\LogAuditTrail::class,
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\MarkAsProcessed::class, // ← processed = true
    \LBHurtado\Voucher\Pipelines\GeneratedVouchers\TriggerPostGenerationWorkflows::class,
],
```

**Key ordering**:
1. `CreateCashEntities` must run **before** `MarkAsProcessed`
2. `MarkAsProcessed` is the final stage

Triggered by event listener:

```php
// packages/voucher/src/Listeners/HandleGeneratedVouchers.php
class HandleGeneratedVouchers implements ShouldQueue // ← Runs asynchronously
{
    public function handle(VouchersGenerated $event): void
    {
        $unprocessed = $event->getVouchers()->filter(fn($v) => !$v->processed);
        
        app(Pipeline::class)
            ->send($unprocessed)
            ->through(config('voucher-pipeline.post-generation'))
            ->thenReturn();
    }
}
```

## Developer Guidelines

### When Generating Vouchers

**DO**:
- ✅ Inform users that vouchers may take a moment to become active
- ✅ Show processing status in voucher list (pending/ready)
- ✅ Allow retry with countdown if user attempts early redemption

**DON'T**:
- ❌ Don't assume vouchers are immediately redeemable
- ❌ Don't skip the processed flag check
- ❌ Don't remove `ShouldQueue` from HandleGeneratedVouchers (defeats purpose)

### When Testing

**Mock queue processing**:
```php
// Prevent async processing
Event::fake([VouchersGenerated::class]);

$voucher = Voucher::createOne([...]);
// Voucher is now unprocessed

// Manually run pipeline
$pipeline = config('voucher-pipeline.post-generation');
app(Pipeline::class)->send(collect([$voucher]))->through($pipeline)->thenReturn();
$voucher->refresh();
// Voucher is now processed
```

**Test race condition**:
```php
public function test_cannot_redeem_unprocessed_voucher()
{
    Event::fake([VouchersGenerated::class]);
    
    $voucher = Voucher::createOne([...]);
    $this->assertFalse($voucher->processed);
    
    $this->expectException(VoucherNotProcessedException::class);
    ProcessRedemption::run($voucher, $phoneNumber, [], []);
}
```

### In Production

**Monitor queue workers**:
```bash
# Ensure queue workers are running
php artisan queue:work --tries=3

# Check for failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

**Edge cases to handle**:
1. **Queue worker down**: Vouchers stuck in "Created" state indefinitely
   - **Mitigation**: Add queue monitoring/alerts
   - **Recovery**: Manually run pipeline or restart queue workers

2. **Pipeline stage fails**: Voucher partially processed
   - **Mitigation**: Pipeline wrapped in DB transaction
   - **Recovery**: Check logs, fix issue, retry job

3. **Bulk generation of 1000+ vouchers**: Processing takes 30+ seconds
   - **Mitigation**: Show progress indicator on generation page
   - **UX**: Display "X of Y vouchers ready" status

## User Experience Best Practices

### Voucher List Page
Show voucher status:
```
┌────────────────────────────────────────┐
│ Voucher Code: ABCD1234                │
│ Amount: ₱100                           │
│ Status: ⏳ Processing... (ready in 3s)│
└────────────────────────────────────────┘
```

### Redemption Flow
If user attempts early redemption:
```
┌────────────────────────────────────────┐
│ ⏱️  Voucher Being Prepared             │
│                                        │
│ This voucher is still being prepared.  │
│ Please wait a moment and try again.    │
│                                        │
│ Retry in 3 seconds...                  │
│                                        │
│ [Retry Now] (enabled after countdown)  │
└────────────────────────────────────────┘
```

### Success Message
After successful redemption:
```
┌────────────────────────────────────────┐
│ ✅ Voucher Redeemed Successfully!      │
│                                        │
│ ₱100.00 has been sent to 09171234567  │
│                                        │
│ Transaction ID: 271749394              │
│ Status: Pending                        │
└────────────────────────────────────────┘
```

## Troubleshooting

### Symptom: Voucher stuck in "Created" state
**Cause**: Queue worker not running or HandleGeneratedVouchers job failed

**Solution**:
```bash
# Check queue status
php artisan queue:work --once --verbose

# Check failed jobs
php artisan queue:failed:table
php artisan queue:failed

# Retry specific job
php artisan queue:retry [job-id]

# Or manually process
php artisan tinker
>>> $voucher = Voucher::where('code', 'ABCD')->first();
>>> $pipeline = config('voucher-pipeline.post-generation');
>>> app(\Illuminate\Pipeline\Pipeline::class)->send(collect([$voucher]))->through($pipeline)->thenReturn();
>>> $voucher->refresh()->processed; // Should be true
```

### Symptom: "Voucher being prepared" message persists forever
**Cause**: Post-generation pipeline stage is failing silently

**Solution**:
```bash
# Check logs for errors
tail -f storage/logs/laravel.log | grep -i "HandleGeneratedVouchers\|CreateCashEntities"

# Manually inspect voucher
php artisan tinker
>>> $voucher = Voucher::where('code', 'ABCD')->first();
>>> $voucher->processed; // false?
>>> $voucher->processed_on; // null?
>>> $voucher->cash; // null?

# Run pipeline with verbose logging
>>> \Illuminate\Support\Facades\Log::info('Manual pipeline run');
>>> $pipeline = config('voucher-pipeline.post-generation');
>>> app(\Illuminate\Pipeline\Pipeline::class)->send(collect([$voucher]))->through($pipeline)->thenReturn();
```

### Symptom: Disbursement didn't happen despite redemption success
**Cause**: Likely the old race condition (pre-v2.x) or cash entity missing

**Solution**:
```bash
# Verify voucher state
php artisan tinker
>>> $voucher = Voucher::where('code', 'ABCD')->first();
>>> $voucher->is_redeemed; // true
>>> $voucher->processed; // true?
>>> $voucher->cash; // exists?
>>> $voucher->metadata['disbursement']; // null if didn't run

# If cash exists but disbursement didn't run, manually trigger
>>> $voucher->cash->mobile = '09171234567';
>>> $voucher->cash->save();
>>> (new \LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash(app(\LBHurtado\PaymentGateway\Contracts\PaymentGatewayInterface::class)))->handle($voucher, fn($v) => $v);
```

## Summary

- ✅ Vouchers must be **processed** before redemption
- ⏱️ Processing takes 2-5 seconds (async queue job)
- ❌ Attempting redemption before processing throws `VoucherNotProcessedException`
- 🔄 Frontend shows retry countdown and button
- 📊 Monitor queue workers to prevent stuck vouchers
- 🧪 Tests verify race condition handling

For more details on the disbursement pipeline, see [`OMNIPAY_INTEGRATION_PLAN.md`](./OMNIPAY_INTEGRATION_PLAN.md).
