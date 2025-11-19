# Navigation Rationalization Plan

## Current State Analysis

### Main Sidebar (AppSidebar.vue)
```
1. Dashboard
2. View Vouchers
3. Generate Vouchers
4. Load Wallet
5. Pricing
6. Transactions
7. Contacts

Footer:
- Redeem
- Github Repo
- Documentation
```

### Settings Sidebar (settings/Layout.vue)
```
1. Profile
2. Appearance
3. Wallet
4. Preferences
5. Campaigns
```

## Best Practices: Verb vs Noun Nomenclature

### Industry Standards (Analyzed from top SaaS products)

**Stripe, GitHub, Notion, Linear, Vercel:**
- **Primary Navigation**: Uses **nouns** for objects/resources (Dashboard, Projects, Users, Settings)
- **Action Buttons**: Uses **verbs** or **verb-noun** (Create Project, Add User, Generate Key)
- **Settings/Secondary**: Uses **nouns** for categories (Profile, Billing, Security, Notifications)

**Best Practice Decision:**
```
✅ Main Navigation → NOUNS (represent sections/resources)
✅ Action Buttons/CTAs → VERBS or VERB-NOUN (represent actions)
✅ Settings Sections → NOUNS (represent settings categories)
```

### Why This Works
1. **Cognitive Load**: Nouns are easier to scan and remember
2. **Hierarchy**: Actions (verbs) are secondary to resources (nouns)
3. **Consistency**: Settings are configurations, not actions
4. **Accessibility**: Screen readers handle nouns better for navigation landmarks

## Problems Identified

### Main Sidebar Issues

| Current | Issue | Type |
|---------|-------|------|
| View Vouchers | Redundant verb ("View" implied) | Verb-Noun |
| Generate Vouchers | Action in main nav (should be button) | Verb-Noun |
| Load Wallet | Action verb (inconsistent) | Verb-Noun |
| Pricing | Admin function in user nav | Noun |
| Contacts | OK | Noun |
| Transactions | OK | Noun |

### Settings Sidebar Issues

| Current | Issue | Type |
|---------|-------|------|
| Profile | OK | Noun |
| Appearance | OK | Noun |
| Wallet | Duplicates main nav concept | Noun |
| Preferences | Too generic, overlaps others | Noun |
| Campaigns | OK | Noun |

### Logical Inconsistencies
1. **"Wallet" appears twice**: Main nav ("Load Wallet") + Settings ("Wallet")
2. **Mixed patterns**: Some items are nouns (Dashboard, Contacts), others are verb-noun (View Vouchers, Generate Vouchers)
3. **Action vs Navigation**: "Generate Vouchers" is an action, not a section
4. **Settings scope confusion**: "Wallet" in settings vs "Load Wallet" in main nav

## Proposed Solution

### Main Sidebar (Refactored)

```
Primary Navigation:
1. Dashboard           [icon: LayoutGrid]
2. Vouchers           [icon: Ticket]        → /vouchers (index)
   - Primary action: "Generate" button in page
3. Wallet             [icon: Wallet]        → /wallet (dashboard)
   - Primary action: "Top Up" button in page
4. Transactions       [icon: Receipt]       → /transactions
5. Contacts           [icon: Users]         → /contacts
6. Billing            [icon: CreditCard]    → /billing (admin)

Footer:
- Redeem              [icon: TicketX]       → /redeem/start
- Help & Support      [icon: HelpCircle]    → /help
- Documentation       [icon: BookOpen]      → external link
```

### Settings Sidebar (Refactored)

```
Settings Sections:
1. Profile            [icon: User]          → /settings/profile
   - Name, email, mobile, webhook, merchant profile
2. Appearance         [icon: Palette]       → /settings/appearance
   - Theme, color mode
3. Notifications      [icon: Bell]          → /settings/notifications
   - Email, SMS, webhook preferences
4. Campaigns          [icon: Folder]        → /settings/campaigns
   - Voucher templates
5. Security           [icon: Shield]        → /settings/security
   - 2FA, sessions, account deletion
```

### Rationale for Changes

#### Main Sidebar

**"View Vouchers" → "Vouchers"**
- Removes redundant "View" verb
- Clicking nav implies viewing
- "Generate" becomes a CTA button in the Vouchers page

**"Generate Vouchers" → Removed from nav**
- Actions don't belong in primary navigation
- Moved to prominent button in Vouchers page header
- Follows Gmail (Compose), GitHub (New), Notion (New Page) pattern

**"Load Wallet" → "Wallet"**
- Noun-based navigation (consistency)
- "Load" becomes a "Top Up" button in Wallet page
- Wallet page shows: balance, history, top-up action

**"Pricing" → "Billing"**
- More standard term (Stripe, AWS, Vercel use "Billing")
- Encompasses pricing, charges, invoices
- Admin-only, can be conditionally shown

**Added "Help & Support"** (optional)
- Replaces generic "Github Repo" link
- Can link to docs, FAQ, contact support

#### Settings Sidebar

**"Wallet" → Removed**
- Redundant with main nav "Wallet"
- Bank account settings can go in Profile or separate "Payment Methods" section

**"Preferences" → Split into "Notifications" and merged elsewhere**
- Too generic and overlapping
- Notification preferences become dedicated section
- Other preferences merge into Profile

**New: "Notifications"**
- Dedicated section for feedback channels
- Email, SMS, webhook configuration
- Follows industry standard (all major SaaS have this)

**New: "Security"** (optional)
- Consolidates 2FA, sessions, account deletion
- Currently "Delete Account" lives in Profile—should be separate
- Industry standard section name

**"Campaigns" → Stays**
- Well-defined, distinct functionality
- No overlap with other sections

## Implementation Plan

### Phase 1: Main Sidebar Refactoring

**1.1 Update AppSidebar.vue**
```typescript
const mainNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    { title: 'Vouchers', href: vouchersIndex.url(), icon: Ticket },
    { title: 'Wallet', href: walletDashboard.url(), icon: Wallet },
    { title: 'Transactions', href: transactionsIndex.url(), icon: Receipt },
    { title: 'Contacts', href: contactsIndex.url(), icon: Users },
    // Conditionally show for admin
    { title: 'Billing', href: billingIndex.url(), icon: CreditCard },
];

const footerNavItems: NavItem[] = [
    { title: 'Redeem', href: redeemStart.url(), icon: TicketX },
    { title: 'Help', href: '/help', icon: HelpCircle },
    { title: 'Docs', href: 'https://docs.example.com', icon: BookOpen },
];
```

**1.2 Create/Update Pages**
- `resources/js/pages/vouchers/Index.vue` - Add prominent "Generate" button in header
- `resources/js/pages/wallet/Dashboard.vue` - Create wallet dashboard with "Top Up" CTA
- `resources/js/pages/billing/Index.vue` - Rename from admin/pricing if needed

**1.3 Update Routes**
```php
// Ensure these routes exist
Route::get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
Route::get('/wallet', [WalletController::class, 'dashboard'])->name('wallet.dashboard');
Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
```

### Phase 2: Settings Sidebar Refactoring

**2.1 Update settings/Layout.vue**
```typescript
const sidebarNavItems: NavItem[] = [
    { title: 'Profile', href: editProfile(), icon: User },
    { title: 'Appearance', href: editAppearance(), icon: Palette },
    { title: 'Notifications', href: editNotifications(), icon: Bell },
    { title: 'Campaigns', href: campaignsIndex.url(), icon: Folder },
    { title: 'Security', href: editSecurity(), icon: Shield },
];
```

**2.2 Restructure Settings Pages**

**Profile (settings/Profile.vue)**
- Keep: Name, Email, Mobile, Webhook
- Keep: Merchant Profile section
- Remove: Delete Account (move to Security)

**Notifications (NEW: settings/Notifications.vue)**
```
Sections:
- Email Notifications (toggle on/off, configure defaults)
- SMS Notifications (toggle, configure defaults)
- Webhook Configuration (URL, events)
- Notification Templates (link to admin section)
```

**Security (NEW: settings/Security.vue)**
```
Sections:
- Two-Factor Authentication
- Active Sessions
- Delete Account (move from Profile)
```

**Wallet Settings (options)**
- Option A: Remove entirely (wallet managed via main nav)
- Option B: Rename to "Payment Methods" with bank account linking
- Option C: Keep but rename to "Bank Accounts"

**2.3 Update Routes**
```php
// routes/settings.php
Route::get('/settings/notifications', [NotificationsController::class, 'edit'])->name('notifications.edit');
Route::patch('/settings/notifications', [NotificationsController::class, 'update'])->name('notifications.update');

Route::get('/settings/security', [SecurityController::class, 'edit'])->name('security.edit');
// Delete account route stays here
```

### Phase 3: Page-Level Action Buttons

**3.1 Vouchers Index Page**
Add prominent header action:
```vue
<PageHeader title="Vouchers" description="Manage your generated vouchers">
    <template #actions>
        <Button as-child size="lg">
            <Link :href="voucherGenerate.url()">
                <Plus class="mr-2 h-4 w-4" />
                Generate Vouchers
            </Link>
        </Button>
    </template>
</PageHeader>
```

**3.2 Wallet Dashboard Page**
Add top-up action:
```vue
<PageHeader title="Wallet" description="Manage your balance and transactions">
    <template #actions>
        <Button as-child size="lg" variant="default">
            <Link :href="topUpCreate.url()">
                <ArrowUpCircle class="mr-2 h-4 w-4" />
                Top Up
            </Link>
        </Button>
    </template>
</PageHeader>
```

### Phase 4: Testing & Verification

**4.1 Navigation Flow Tests**
- Verify all main nav items route correctly
- Verify settings nav items route correctly
- Test breadcrumbs update correctly
- Test active states highlight correctly

**4.2 Action Button Tests**
- Verify "Generate Vouchers" button in Vouchers page
- Verify "Top Up" button in Wallet page
- Test keyboard navigation
- Test screen reader announcements

**4.3 Responsive Tests**
- Mobile sidebar collapse behavior
- Settings sidebar stacking on mobile
- Action buttons responsive sizing

## Migration Notes

### Breaking Changes
- URLs remain the same (no breaking changes)
- Navigation labels change (user-facing)
- Settings page structure changes

### Backwards Compatibility
- All existing routes continue to work
- Redirects not needed (URLs unchanged)
- Deep links remain valid

### User Communication
- Release notes: "Navigation simplified for clarity"
- Highlight: Actions moved to page-level buttons
- Guide: "Find Generate Vouchers in the Vouchers page"

## Alternative Considerations

### Alternative 1: Keep Verb-Noun Pattern Everywhere
```
Pros: Explicit about what you can do
Cons: Verbose, harder to scan, not industry standard
Verdict: ❌ Reject
```

### Alternative 2: Use Icons Only (No Text)
```
Pros: Clean, minimal
Cons: Poor discoverability, accessibility issues
Verdict: ❌ Reject
```

### Alternative 3: Hybrid (Nouns + Quick Actions)
```
Pros: Best of both worlds
Cons: Could clutter sidebar
Verdict: ⚠️ Consider for future iteration
Example:
  Vouchers [+ Generate icon button inline]
```

## Recommended Decision

**✅ Proceed with Proposed Solution (Noun-based Navigation)**

**Reasoning:**
1. Aligns with industry standards (Stripe, GitHub, Vercel, Notion)
2. Reduces cognitive load
3. Scales better as features grow
4. Improves accessibility
5. Separates navigation (nouns) from actions (verbs)
6. Makes primary actions more prominent (page-level CTAs)

**Next Steps:**
1. ✅ Review this plan
2. ⏳ Implement Phase 1 (Main Sidebar)
3. ⏳ Implement Phase 2 (Settings Sidebar)
4. ⏳ Implement Phase 3 (Action Buttons)
5. ⏳ Test & Verify
6. ⏳ Commit & Deploy
