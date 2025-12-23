import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface Features {
    widget: boolean;
    workflow: boolean;
    actions: boolean;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface CurrentTeam {
    id: number;
    name: string;
    is_owner: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    tenant: string;
    features: Features;
    subscribed: boolean;
    onTrial: boolean;
    trialEndsAt?: string | null;
    subscriptionStartsAt?: string | null;
    permissions: Record<number, string[]>;
    currentTeam: CurrentTeam | null;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    is_paid: boolean;
    type: 'admin' | 'company' | 'staff';
    capabilities?: App.Data.Resources.Billing.BillingPackageCapabilitiesData;
}

export type BreadcrumbItemType = BreadcrumbItem;
