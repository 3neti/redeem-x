import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    roles: string[];
    permissions: string[];
    feature_flags: {
        advanced_pricing_mode: boolean;
        beta_features: boolean;
    };
    is_admin_override: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title?: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    type?: 'separator' | 'item';
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
};

export interface VendorAlias {
    id: number;
    alias: string;
    owner_user_id: number;
    status: 'active' | 'revoked' | 'reserved';
    assigned_by_user_id: number | null;
    assigned_at: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    wallet?: {
        id: number;
        balance: number;
    };
    primary_vendor_alias?: VendorAlias;
}

export type BreadcrumbItemType = BreadcrumbItem;
