# Form Variables Implementation - Lessons Learned

## Overview
This document captures key lessons from implementing the variables block feature for form field configuration in the Form Flow Manager package.

## Feature Summary
Added support for centralized variable configuration with server-side resolution:

```javascript
{
  handler: 'form',
  config: {
    variables: {
      $country: 'PH',
      $rail: 'PESONET',
      $defaultAmount: 100,
      $minAmount: 50,
      $maxAmount: 50000
    },
    fields: [
      { name: 'amount', type: 'number', default: '$defaultAmount', min: '$minAmount', max: '$maxAmount' },
      { name: 'country', type: 'recipient_country', default: '$country', readonly: true }
    ]
  }
}
```

## Key Lessons

### 1. Inertia.js Component Lifecycle Gotcha
**Problem**: Form fields showed defaults after page refresh but not on initial Inertia navigation.

**Root Cause**: Vue `<script setup>` only executes once when component first mounts, not when Inertia swaps props during navigation.

**Solution**: Use `watch()` to detect prop changes and re-initialize form data:
```typescript
// Initialize immediately
initializeFormData();

// Re-initialize when fields change (Inertia navigation)
watch(() => props.fields, () => {
    console.log('[GenericForm] Fields changed, re-initializing form data');
    initializeFormData();
}, { deep: true });
```

**Lesson**: Always consider Inertia's prop-swapping behavior when initializing component state. Don't assume setup code only needs to run once.

### 2. Component Prop Type Safety
**Problem**: Vue warnings about receiving `undefined` when components expected `string` props.

**Root Cause**: Financial components (`CountrySelect`, `BankEMISelect`) had required string props but received `undefined` during initialization.

**Solution**: Make props optional and provide fallback values:
```typescript
// Before (strict)
interface Props {
    modelValue: string;
}

// After (flexible)
interface Props {
    modelValue?: string;
}
const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
});

const localValue = computed({
    get: () => props.modelValue || '',
    set: (value) => emit('update:modelValue', value),
});
```

**Lesson**: Vue components used in dynamic forms should gracefully handle undefined/null values, especially during initialization.

### 3. Backend Variable Resolution Context
**Problem**: Variables weren't being resolved in validation rules, causing errors like `"$minAmount" does not represent a valid number`.

**Root Cause**: The `handle()` method (for validation) and `render()` method (for display) both needed access to `collected_data` for Phase 2 context variables, but `FormFlowController` wasn't passing it to both contexts.

**Solution**: Ensure both render and validation contexts receive collected_data:
```php
// FormFlowController.php
$handler->render($step, [
    'flow_id' => $flowId,
    'step_index' => $currentStep,
    'collected_data' => $state['collected_data'] ?? [],
]);

$data = $handler->handle($request, $step, [
    'flow_id' => $flowId,
    'collected_data' => $state['collected_data'] ?? [],
]);
```

**Lesson**: When adding context-aware features, audit all code paths to ensure context is consistently available.

### 4. Debug Logging Strategy
**Problem**: Initial issue was unclear - defaults worked on refresh but not on navigation.

**Root Cause**: Couldn't see what backend was sending vs. what frontend received.

**Solution**: Strategic debug logging at key points:
- Backend: Log resolved fields before sending to Inertia
- Frontend: Log field initialization and prop changes
- Compare logs to identify where data was lost

**Lesson**: Add temporary debug logging at system boundaries (backend→frontend, props→state) when debugging lifecycle issues.

### 5. Testing Initialization Issues
**Problem**: Hard to reproduce and diagnose initialization timing issues.

**Best Practices**:
1. Test with fresh browser tabs (not just refresh)
2. Test Inertia navigation (multi-step flows)
3. Check console immediately on page load
4. Clear browser cache completely when making frontend changes
5. Verify build artifacts were actually updated (check file timestamps)

**Lesson**: Frontend initialization bugs often hide behind cache layers. Test in a pristine environment.

## Implementation Checklist
For future similar features:

- [ ] Backend resolves data correctly (add logging)
- [ ] Frontend initializes state in `setup()`
- [ ] Frontend watches for prop changes (Inertia navigation)
- [ ] Components handle undefined/null props gracefully
- [ ] Context passed to all code paths (render + validation)
- [ ] Build artifacts updated (`npm run build`)
- [ ] Browser cache cleared
- [ ] Test fresh navigation flow (not just refresh)
- [ ] Unit tests cover all scenarios
- [ ] Remove debug logging before commit

## Files Modified
- `packages/form-flow-manager/src/Handlers/FormHandler.php` - Variable resolution logic
- `packages/form-flow-manager/src/Http/Controllers/FormFlowController.php` - Context passing
- `resources/js/pages/form-flow/core/GenericForm.vue` - Initialization + watch
- `resources/js/components/financial/*.vue` - Optional props
- `public/form-flow-demo.html` - Demo with variables

## Testing
- Unit tests: `FormHandlerVariableResolutionTest.php` (16 tests, 35 assertions)
- Manual testing: Demo flow with demographics + financial info
- Edge cases: Phase 2 context variables ($step0_fieldname)

## References
- Inertia.js docs: https://inertiajs.com/
- Vue 3 Composition API: https://vuejs.org/guide/extras/composition-api-faq.html
- Original feature request: Variables block for form field configuration
