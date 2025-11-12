<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { create as voucherGenerate } from '@/actions/App/Http/Controllers/VoucherGenerationController';
import { index as vouchersIndex } from '@/actions/App/Http/Controllers/Voucher/VoucherController';
import { index as contactsIndex } from '@/actions/App/Http/Controllers/ContactController';
import { index as transactionsIndex } from '@/actions/App/Http/Controllers/TransactionController';
import { start as redeemStart } from '@/actions/App/Http/Controllers/Redeem/RedeemController';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Ticket, BadgeDollarSign, List, Users, Receipt, DollarSign, Wallet } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'View Vouchers',
        href: vouchersIndex.url(),
        icon: List,
    },
    {
        title: 'Generate Vouchers',
        href: voucherGenerate.url(),
        icon: Ticket,
    },
    {
        title: 'Load Wallet',
        href: '/wallet/load',
        icon: Wallet,
    },
    {
        title: 'Pricing',
        href: '/admin/pricing',
        icon: DollarSign,
    },
    {
        title: 'Transactions',
        href: transactionsIndex.url(),
        icon: Receipt,
    },
    {
        title: 'Contacts',
        href: contactsIndex.url(),
        icon: Users,
    },
    {
        title: 'Redeem Voucher',
        href: redeemStart.url(),
        icon: BadgeDollarSign,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
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

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
