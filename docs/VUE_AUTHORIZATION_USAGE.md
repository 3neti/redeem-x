# Vue Authorization Usage Guide

How to use the new authorization system in Vue components.

---

## Available Props

All Vue pages receive these via Inertia's shared data:

```typescript
$page.props.auth = {
  user: User,                          // Current user object
  roles: string[],                     // User's roles: ['super-admin', 'admin', 'power-user', 'basic-user']
  permissions: string[],               // User's permissions: ['manage pricing', 'view balance', etc.]
  feature_flags: {
    advanced_pricing_mode: boolean,    // true for super-admin/power-user
    beta_features: boolean,            // false by default (manual activation)
  },
  is_admin_override: boolean,          // true if user has admin role OR is in ADMIN_OVERRIDE_EMAILS
}
```

---

## Common Use Cases

### 1. Check if User is Admin

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

// Check if user has admin access (role-based OR override)
const isAdmin = computed(() => page.props.auth?.is_admin_override || false);

// Check specific role
const isSuperAdmin = computed(() => {
  const roles = page.props.auth?.roles || [];
  return roles.includes('super-admin');
});
</script>

<template>
  <div v-if="isAdmin">
    <!-- Admin-only content -->
  </div>
</template>
```

### 2. Check Permissions

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const canManagePricing = computed(() => {
  const permissions = page.props.auth?.permissions || [];
  return permissions.includes('manage pricing');
});

const canViewBalance = computed(() => {
  const permissions = page.props.auth?.permissions || [];
  return permissions.includes('view balance');
});
</script>

<template>
  <button v-if="canManagePricing" @click="editPricing">
    Edit Pricing
  </button>
  
  <div v-if="canViewBalance">
    <!-- Balance monitoring dashboard -->
  </div>
</template>
```

### 3. Use Feature Flags

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const hasAdvancedMode = computed(() => 
  page.props.auth?.feature_flags?.advanced_pricing_mode || false
);

const hasBetaFeatures = computed(() => 
  page.props.auth?.feature_flags?.beta_features || false
);
</script>

<template>
  <!-- Show advanced options only if feature flag is enabled -->
  <div v-if="hasAdvancedMode">
    <h2>Advanced Pricing Options</h2>
    <!-- Advanced controls -->
  </div>
  
  <!-- Show beta features if enabled -->
  <div v-if="hasBetaFeatures">
    <span class="badge">Beta</span>
    <!-- Beta feature UI -->
  </div>
</template>
```

### 4. Conditional Navigation Items

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const navItems = computed(() => {
  const items = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vouchers', href: '/vouchers' },
  ];
  
  // Add admin items if user has admin access
  const isAdmin = page.props.auth?.is_admin_override || false;
  if (isAdmin) {
    items.push({ title: 'Pricing', href: '/admin/pricing' });
    items.push({ title: 'Balances', href: '/balances' });
  }
  
  return items;
});
</script>

<template>
  <nav>
    <a v-for="item in navItems" :key="item.href" :href="item.href">
      {{ item.title }}
    </a>
  </nav>
</template>
```

### 5. Multiple Role Check

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const hasAnyAdminRole = computed(() => {
  const roles = page.props.auth?.roles || [];
  return roles.some(role => 
    ['super-admin', 'admin', 'power-user'].includes(role)
  );
});

const hasRole = (roleName: string) => {
  const roles = page.props.auth?.roles || [];
  return roles.includes(roleName);
};
</script>

<template>
  <div v-if="hasAnyAdminRole">
    <!-- Admin section -->
  </div>
  
  <button v-if="hasRole('super-admin')" @click="dangerousAction">
    Super Admin Only
  </button>
</template>
```

---

## Composables (Recommended)

Create reusable composables for authorization checks:

```typescript
// composables/useAuth.ts
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
  const page = usePage();
  
  const user = computed(() => page.props.auth?.user);
  const roles = computed(() => page.props.auth?.roles || []);
  const permissions = computed(() => page.props.auth?.permissions || []);
  const featureFlags = computed(() => page.props.auth?.feature_flags || {});
  const isAdmin = computed(() => page.props.auth?.is_admin_override || false);
  
  const hasRole = (role: string) => roles.value.includes(role);
  const hasPermission = (permission: string) => permissions.value.includes(permission);
  const hasAnyRole = (roleList: string[]) => roleList.some(role => hasRole(role));
  const hasFeature = (flag: keyof typeof featureFlags.value) => featureFlags.value[flag] || false;
  
  return {
    user,
    roles,
    permissions,
    featureFlags,
    isAdmin,
    hasRole,
    hasPermission,
    hasAnyRole,
    hasFeature,
  };
}
```

### Using the Composable

```vue
<script setup lang="ts">
import { useAuth } from '@/composables/useAuth';

const { isAdmin, hasRole, hasPermission, hasFeature } = useAuth();
</script>

<template>
  <div v-if="isAdmin">Admin Panel</div>
  <div v-if="hasRole('super-admin')">Super Admin Section</div>
  <div v-if="hasPermission('manage pricing')">Pricing Editor</div>
  <div v-if="hasFeature('advanced_pricing_mode')">Advanced Options</div>
</template>
```

---

## Real-World Examples

### AppSidebar.vue (Already Implemented)

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const isSuperAdmin = computed(() => {
  const roles = page.props.auth?.roles || [];
  return roles.includes('super-admin');
});

const isAdminOverride = computed(() => 
  page.props.auth?.is_admin_override || false
);

const hasAdminAccess = computed(() => 
  isSuperAdmin.value || isAdminOverride.value
);

const permissions = computed(() => 
  page.props.auth?.permissions || []
);

const mainNavItems = computed(() => {
  const items = [/* ... */];
  
  // Add admin section if user has admin access
  if (hasAdminAccess.value) {
    if (isAdminOverride.value || permissions.value.includes('manage pricing')) {
      items.push({ title: 'Pricing', href: '/admin/pricing' });
    }
  }
  
  return items;
});
</script>
```

### Voucher Generate Page

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

// Use feature flag to determine UI mode
const showAdvancedMode = computed(() => 
  page.props.auth?.feature_flags?.advanced_pricing_mode || false
);

// Store mode in local state (user can toggle)
const mode = ref(showAdvancedMode.value ? 'advanced' : 'simple');
</script>

<template>
  <div v-if="mode === 'simple'">
    <!-- Simple form with 3-5 essential fields -->
    <button v-if="showAdvancedMode" @click="mode = 'advanced'">
      Switch to Advanced Mode
    </button>
  </div>
  
  <div v-else>
    <!-- Full form with all options -->
    <button @click="mode = 'simple'">
      Switch to Simple Mode
    </button>
  </div>
</template>
```

---

## Testing in Dev Tools

Check authorization data in browser console:

```javascript
// Access auth data
$page.props.auth

// Check specific role
$page.props.auth.roles.includes('super-admin')

// Check permission
$page.props.auth.permissions.includes('manage pricing')

// Check feature flag
$page.props.auth.feature_flags.advanced_pricing_mode

// Check admin access
$page.props.auth.is_admin_override
```

---

## Best Practices

1. **Use Computed Properties**: Always wrap auth checks in `computed()` for reactivity
2. **Fallback Values**: Always provide fallback values (e.g., `|| []`, `|| false`)
3. **Type Safety**: Use TypeScript for full type checking
4. **Composables**: Extract reusable logic into composables
5. **Server-Side First**: Always enforce authorization on backend (never trust frontend)
6. **Feature Flags**: Use feature flags for gradual rollouts and A/B testing

---

## Migration Notes

### Old Approach (Deprecated)
```vue
<!-- ❌ Old: Only checked .env override -->
<div v-if="$page.props.auth.is_admin_override">
  Admin content
</div>
```

### New Approach (Current)
```vue
<!-- ✅ New: Checks both roles AND override -->
<div v-if="$page.props.auth.is_admin_override">
  Admin content (works with roles OR .env)
</div>

<!-- ✅ Better: Check specific role/permission -->
<div v-if="hasRole('super-admin')">
  Super admin content
</div>

<div v-if="hasPermission('manage pricing')">
  Pricing editor
</div>
```

---

## Summary

- ✅ All auth data available in `$page.props.auth`
- ✅ Backward compatible: `is_admin_override` checks both roles AND .env
- ✅ Type-safe with TypeScript definitions
- ✅ Feature flags ready for gradual rollouts
- ✅ No component changes needed (existing code works)

For questions, see `MIGRATION_COMPLETE.md` or `AUTHORIZATION_STRATEGY.md`.
