<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import NavBalance from '@/components/NavBalance.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { index as vouchersIndex } from '@/actions/App/Http/Controllers/Vouchers/VoucherController';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/Contacts/ContactController';
import { index as transactionsIndex } from '@/actions/App/Http/Controllers/Transactions/TransactionController';
import { start as redeemStart } from '@/actions/App/Http/Controllers/Redeem/RedeemController';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BookOpen, LayoutGrid, Ticket, Users, Receipt, Wallet, TicketX, HelpCircle, DollarSign, Settings2, BarChart3 } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Debug flag
const DEBUG = true;

const page = usePage();
const showBalance = computed(() => page.props.sidebar?.balance?.show ?? true);

// Check if user has super-admin role or is in admin override list
const isSuperAdmin = computed(() => {
    const roles = page.props.auth?.roles || [];
    return roles.includes('super-admin');
});

const isAdminOverride = computed(() => page.props.auth?.is_admin_override || false);

const hasAdminAccess = computed(() => isSuperAdmin.value || isAdminOverride.value);

const permissions = computed(() => page.props.auth?.permissions || []);

const mainNavItems = computed<NavItem[]>(() => {
    // Debug logging
    if (DEBUG && import.meta.env.DEV) {
        console.log('[AppSidebar] Auth props:', page.props.auth);
        console.log('[AppSidebar] Roles:', page.props.auth?.roles);
        console.log('[AppSidebar] Is super admin:', isSuperAdmin.value);
        console.log('[AppSidebar] Permissions:', permissions.value);
    }
    
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Vouchers',
            href: vouchersIndex.url(),
            icon: Ticket,
        },
        {
            title: 'Wallet',
            href: '/wallet',
            icon: Wallet,
        },
        {
            title: 'Transactions',
            href: transactionsIndex.url(),
            icon: Receipt,
        },
        {
            title: 'Reports',
            href: '/reports',
            icon: BarChart3,
        },
        {
            title: 'Contacts',
            href: contactsIndex.url(),
            icon: Users,
        },
    ];

    // Add admin section if user is super-admin or has admin override
    if (hasAdminAccess.value) {
        // Add visual separator
        items.push({ type: 'separator' } as NavItem);
        
        if (isAdminOverride.value || permissions.value.includes('manage pricing')) {
            items.push({
                title: 'Pricing',
                href: '/admin/pricing',
                icon: DollarSign,
            });
        }
        
        if (isAdminOverride.value || permissions.value.includes('manage preferences')) {
            items.push({
                title: 'Preferences',
                href: '/admin/preferences',
                icon: Settings2,
            });
        }
    }

    return items;
});

const redemptionEndpoint = computed(() => page.props.redemption_endpoint || '/disburse');

const footerNavItems = computed<NavItem[]>(() => [
    {
        title: 'Redeem',
        href: redemptionEndpoint.value,
        icon: TicketX,
    },
    {
        title: 'Documentation',
        href: '/documentation',
        icon: BookOpen,
    },
    {
        title: 'Help',
        href: '/help',
        icon: HelpCircle,
    },
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <NavBalance v-if="showBalance" />

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
