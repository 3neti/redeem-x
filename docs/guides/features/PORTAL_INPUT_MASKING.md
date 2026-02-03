# Portal Input Masking: State Machine Documentation

## Overview

The Portal page (`/portal`) implements a **calculator-style input system** for instant voucher generation. The input field displays `amount x count` format (e.g., `100 x 1`) with sophisticated state machine logic for editing both values seamlessly.

**Key File:** `resources/js/pages/Portal.vue`

## Architecture

### State Machine Components

```typescript
type EditMode = 'amount' | 'count';
const editMode = ref<EditMode>('amount');
const tempAmount = ref<string>('');  // Digit accumulator for amount
const tempCount = ref<string>('1');  // Digit accumulator for count
```

### Core Principles

1. **Dual Mode Editing**: Toggle between editing amount and count using non-numeric keys
2. **Accumulator Pattern**: Empty accumulator = "select all" behavior (next digit replaces)
3. **Global Keyboard Capture**: Input works without clicking field first (calculator-style)
4. **Sticky Count**: Count persists across amount changes unless explicitly edited

## User Interaction Flow

### Basic Flow: Edit Amount

```
Initial State: Empty field, quick amounts visible
User clicks "100"
  → Display: "100 x 1"
  → tempAmount: "" (empty, ready for replace)
  → tempCount: "1"
  → editMode: 'amount'

User types: 2
  → tempAmount === "" → First digit replaces
  → tempAmount: "2"
  → Display: "2 x 1" ✅

User types: 0
  → tempAmount: "20"
  → Display: "20 x 1"

User types: 5
  → tempAmount: "205"
  → Display: "205 x 1"
```

### Advanced Flow: Toggle to Count

```
Current: "205 x 1"

User types: x (or any non-numeric)
  → editMode: 'count'
  → tempCount: "" (reset for select-all)
  → Display: "205 x 1" (no change)

User types: 3
  → tempCount === "" → First digit replaces
  → tempCount: "3"
  → Display: "205 x 3" ✅

User types: 0
  → tempCount: "30"
  → Display: "205 x 30"
```

### Toggle Back: Count Stays Sticky

```
Current: "205 x 30"

User types: space (or any non-numeric)
  → editMode: 'amount'
  → tempAmount: "" (reset for select-all)
  → Display: "205 x 30" (no change)

User types: 1
  → tempAmount === "" → First digit replaces
  → tempAmount: "1"
  → Display: "1 x 30" ✅ (count preserved!)

User types: 0
  → tempAmount: "10"
  → Display: "10 x 30"

User types: 0
  → tempAmount: "100"
  → Display: "100 x 30"
```

### Backspace Behavior

```
Current: "100 x 30" (editMode: 'amount')

User presses: Backspace
  → If tempAmount is empty, populate from display
  → tempAmount: "100" → "10"
  → Display: "10 x 30"

User presses: Backspace
  → tempAmount: "10" → "1"
  → Display: "1 x 30"

User presses: Backspace
  → tempAmount: "1" → ""
  → amount.value: null
  → instruction.value: "" (cleared)
  → Quick amounts reappear! ✅
```

## Implementation Details

### Key Functions

#### `handleKeyDown(event: KeyboardEvent)`

Main state machine handler. Processes:
- **Numeric keys (0-9)**: Append to current mode's accumulator
- **Backspace**: Remove last digit, clear instruction if amount becomes null
- **Enter**: Submit form (propagates)
- **Any other key**: Toggle between amount/count mode

#### `handleQuickAmount(amt: number)`

Sets up state after quick amount click:
```typescript
amount.value = amt;
count.value = 1;
tempAmount.value = '';  // Empty = next digit replaces
tempCount.value = '1';
editMode.value = 'amount';
instruction.value = `${amt} x 1`;
```

#### `handleGlobalKeyDown(event: KeyboardEvent)`

Global keyboard capture wrapper:
- Attached to `window` in `onMounted()`
- Forwards keystrokes to `handleKeyDown()` if no modal is open
- Skips if user is typing in another input field

#### `handleInputClick(event: MouseEvent)`

Click detection to set mode:
- Click before "x" → `editMode = 'amount'`
- Click after "x" → `editMode = 'count'`

### Display Logic

```typescript
// Show quick amounts when both are empty
const showQuickAmounts = computed(() => !instruction.value && !amount.value);

// Update display
instruction.value = `${tempAmount.value} x ${tempCount.value}`;
```

### Edge Cases Handled

1. **Empty accumulator + backspace**: Populate from display first
2. **Amount backspaced to null**: Clear instruction to show quick amounts
3. **Count can't be empty**: Defaults to "1" if backspaced completely
4. **Global capture**: Disabled when modal open or typing in another field

## Live Pricing Integration

The count multiplies into the pricing calculation:

```typescript
const instructionsForPricing = computed(() => ({
  cash: { amount: amount.value || 0, currency: 'PHP' },
  inputs: { fields: quickInputs.value },
  count: count.value,  // Multiplies pricing
}));
```

Display shows: `₱100 x 5 + ₱20.00 fee = ₱520.00`

## Visual Feedback

- **Input field**: `ring-2 ring-primary/50` class indicates "always active"
- **Quick amounts**: Embedded inside field, disappear when typing
- **Placeholder**: Dynamic, reflects current state (amount, count, inputs)

## Testing Checklist

- [ ] Click quick amount → shows `100 x 1`
- [ ] Type digit → replaces amount (select-all)
- [ ] Type multiple digits → appends correctly
- [ ] Type "x" → toggles to count mode (no visual change)
- [ ] Type digit in count → replaces count (select-all)
- [ ] Type non-numeric → toggles back to amount
- [ ] Type digit → replaces amount, count stays sticky
- [ ] Backspace in amount → removes digit, shows quick amounts when empty
- [ ] Backspace in count → removes digit, minimum is "1"
- [ ] Global keyboard → works without clicking field
- [ ] Modal open → keyboard capture disabled
- [ ] Press Enter → submits form
- [ ] Pricing → multiplies by count correctly

## Future Enhancements (Optional)

### Configuration Mode Toggle

The current implementation could support multiple modes:

1. **Append Mode** (current alternative): First digit after quick amount appends instead of replaces
2. **Select-All Mode** (current default): First digit always replaces

Configuration could be added via:
```typescript
const inputMode = ref<'append' | 'select-all'>('select-all');
```

To switch between modes, change `handleQuickAmount()`:
```typescript
// Append mode
tempAmount.value = amt.toString(); // Populated, not empty

// Select-all mode
tempAmount.value = ''; // Empty, next digit replaces
```

### Visual Mode Indicator

Show subtle indicator for current edit mode:
```vue
<div class="text-xs text-muted-foreground">
  Editing: {{ editMode === 'amount' ? 'Amount' : 'Count' }}
</div>
```

### Keyboard Shortcuts

- `Cmd/Ctrl + A`: Select amount portion
- `Cmd/Ctrl + C`: Select count portion
- `Cmd/Ctrl + R`: Reset to quick amounts

## Related Files

- **Implementation**: `resources/js/pages/Portal.vue` (lines 36-396)
- **Pricing Logic**: `resources/js/composables/useChargeBreakdown.ts`
- **API Endpoint**: `POST /api/v1/vouchers` (accepts `count` parameter)
- **Documentation**: `docs/PORTAL_INPUT_MASKING.md` (this file)

## Troubleshooting

### Issue: Quick amounts don't reappear after backspace

**Cause**: `instruction.value` not cleared when `amount.value` becomes null

**Fix**: In `handleKeyDown()` backspace handler:
```typescript
if (!amount.value) {
  instruction.value = '';
  return;
}
```

### Issue: First digit appends instead of replaces

**Cause**: `tempAmount` not reset to empty string

**Fix**: Ensure accumulator is empty when mode toggles or quick amount is clicked:
```typescript
tempAmount.value = ''; // Reset for select-all
```

### Issue: Count doesn't stay sticky

**Cause**: Count being reset to 1 on amount change

**Fix**: Only reset count explicitly, not in watch or backspace handlers. The accumulator pattern handles this automatically.

## Git History

- **Initial Implementation**: `feature/portal-cash-register-ui`
- **State Machine**: Commit with "Implement state machine for amount/count editing"
- **Select-All Fix**: Commit with "Fix display logic to not fall back to old amount value"
- **Quick Amounts Fix**: Commit with "Clear instruction when amount backspaced to null"
