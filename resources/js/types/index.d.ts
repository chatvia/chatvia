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
    subscribed: boolean;
    onTrial: boolean;
    trialEndsAt?: string | null;
    subscriptionStartsAt?: string | null;
    permissions: Record<number, string[]>;
    currentTeam: CurrentTeam | null;

    // Chat-flow / widget contexts share the resolved agent and surface flash
    // messages, request schema, and the active subdomain. Most pages don't
    // see these but typing them here keeps `$page.props.X` honest.
    agent?: {
        id: number;
        external_id: string;
        name: string;
        [key: string]: unknown;
    };
    schema?: string;
    environment?: string;
    domain?: string;
    subdomain?: string | null;
    flash?: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };

    // Server-side validation errors. Inertia adds these on every visit and
    // are surfaced both at `$page.props.errors` and via `useForm`.
    errors?: Record<string, string>;
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
    // Preferred date display format key — see Support\Enums\User\DateFormat and
    // the useDateFormat composable. Null/undefined falls back to ISO.
    date_format?: string | null;
    capabilities?: App.Billing.Data.Resources.Package.BillingPackageCapabilitiesData;
}

export type BreadcrumbItemType = BreadcrumbItem;
