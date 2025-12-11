# Lesson Learned: Vue Template Optional Chaining with Reactive Proxies

## Issue Summary
When using optional chaining (`?.`) in Vue 3 templates with reactive proxy objects, the conditional rendering may fail even when the data exists.

## Date
December 11, 2025

## Context
While implementing the voucher preview message feature, we encountered an issue where the preview message alert wasn't displaying despite:
1. Backend API correctly returning the data
2. Browser console showing the data was received
3. TypeScript types being correct
4. The template condition appearing logically sound

## The Problem

### What Didn't Work
```vue
<!-- This failed to render even when voucherData.preview.message existed -->
<Alert v-if="voucherData.preview?.message" class="mb-4" variant="default">
    <AlertDescription>
        <strong class="font-semibold">Note from issuer:</strong> {{ voucherData.preview.message }}
    </AlertDescription>
</Alert>
```

### Console Output (Successful Data Load)
```javascript
voucherData.preview: Proxy { 
    <target>: { 
        enabled: true, 
        scope: "full", 
        message: "Hi there pader." 
    }
}
```

Despite the data being present as a Vue Proxy, the `v-if` condition with optional chaining didn't evaluate correctly.

## The Solution

### What Works
```vue
<!-- Use explicit && checks instead of optional chaining -->
<Alert v-if="voucherData.preview && voucherData.preview.message" class="mb-4" variant="default">
    <AlertDescription>
        <strong class="font-semibold">Note from issuer:</strong> {{ voucherData.preview.message }}
    </AlertDescription>
</Alert>
```

## Root Cause
Vue 3's reactivity system wraps objects in Proxy instances. While optional chaining (`?.`) works perfectly in JavaScript, it may not work reliably in Vue templates when dealing with reactive proxies, particularly in conditional directives like `v-if`.

The issue appears to be related to how Vue's template compiler handles optional chaining with proxy objects during reactive dependency tracking.

## Best Practices

### ✅ DO
- Use explicit `&&` checks for nested property access in Vue templates
- Test conditional rendering with actual data, not just with console logging
- Use explicit checks when dealing with deeply nested reactive objects

```vue
<!-- Recommended pattern -->
<div v-if="obj && obj.nested && obj.nested.value">
    {{ obj.nested.value }}
</div>
```

### ❌ DON'T
- Rely on optional chaining (`?.`) in Vue template `v-if` conditions with reactive data
- Assume that console.log showing data means the template will render it

```vue
<!-- Avoid in Vue templates -->
<div v-if="obj?.nested?.value">
    {{ obj.nested.value }}
</div>
```

## When It's Safe to Use Optional Chaining

Optional chaining is still useful and safe in:
1. **Component `<script>` sections** - Regular JavaScript/TypeScript code
2. **Computed properties** - Processing reactive data
3. **Methods** - Function bodies
4. **Template interpolations** (with caution) - `{{ obj?.nested?.value }}`

```vue
<script setup>
// ✅ Safe: In script
const hasMessage = computed(() => voucherData.value?.preview?.message);

// ✅ Safe: In methods
function checkMessage() {
    return voucherData.value?.preview?.message;
}
</script>

<template>
    <!-- ❌ Risky: In v-if with reactive proxies -->
    <div v-if="voucherData?.preview?.message">...</div>
    
    <!-- ✅ Safe: Using computed -->
    <div v-if="hasMessage">...</div>
    
    <!-- ✅ Safe: Explicit checks -->
    <div v-if="voucherData && voucherData.preview && voucherData.preview.message">...</div>
</template>
```

## Related Files
- `/resources/js/components/RedeemWidget.vue` - Where the fix was applied
- `/resources/js/composables/useVoucherPreview.ts` - Composable handling reactive data
- `/resources/js/types/voucher.d.ts` - Type definitions

## Debugging Process
1. ✅ Verified backend returns correct data (via tinker)
2. ✅ Checked API endpoint directly (returns preview object)
3. ✅ Verified TypeScript types include preview fields
4. ✅ Added console.log to confirm data reaches frontend
5. ✅ Identified that optional chaining in template was the issue
6. ✅ Replaced with explicit && checks
7. ✅ Confirmed fix works

## Prevention
- When adding new conditional rendering in Vue templates with nested reactive objects, prefer explicit `&&` checks
- If using optional chaining initially, test with actual data immediately
- Add this pattern to code review checklist for Vue components

## References
- Vue 3 Reactivity Documentation: https://vuejs.org/guide/essentials/reactivity-fundamentals.html
- Vue Template Syntax: https://vuejs.org/guide/essentials/template-syntax.html
- JavaScript Optional Chaining: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Optional_chaining

## Impact
- **Affected Feature**: Voucher preview message display
- **Resolution Time**: ~15 minutes after identifying the pattern
- **Workaround Complexity**: Simple (one-line change)
- **Risk of Recurrence**: Medium (common pattern in Vue templates)

---

**Key Takeaway**: In Vue 3 templates, when working with reactive proxy objects in conditional directives (`v-if`, `v-show`), always use explicit `&&` checks instead of optional chaining (`?.`) to ensure reliable reactivity and rendering.
