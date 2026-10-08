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

// Product name and links from config/chatvia.php `brand`. URLs arrive as set
// in the environment: null (or an empty string) means "hide that link". Read
// them through the `useBrand()` composable, which turns empty strings into null.
export interface Brand {
    name: string;
    website_url: string | null;
    docs_url: string | null;
    api_docs_url: string | null;
    powered_by: {
        enabled: boolean;
        label: string | null;
        url: string | null;
    };
    onboarding_video_url: string | null;
    marketing_site: boolean;
}

export interface SharedData extends PageProps {
    name: string;
    brand?: Brand;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    tenant: string;
    subscribed: boolean;
    // Paid plans on/off (config/billing.php). Read it through `useBilling()`.
    billing?: { enabled: boolean };
    permissions: Record<number, string[]>;
    currentTeam: CurrentTeam | null;
    // Persisted UI language for the signed-in user (`auto` follows the browser).
    locale?: string;
    // Pennant feature flags, keyed by feature name (e.g. `security`). Resolved
    // per request from the workspace's live plan entitlement.
    features: Record<string, boolean>;

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
    // Stable, non-guessable public identifier (UUID). The integer `id` stays
    // internal; this is what's exposed through the API.
    external_id: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    is_paid: boolean;
    // Mirrors Support\Enums\User\UserType. `workspace` = account owner,
    // `employee` = invited teammate/collaborator, `chatter` = widget end-user,
    // `admin` = internal Chatvia staff.
    type: 'admin' | 'workspace' | 'employee' | 'chatter';
    // Preferred date display format key — see Support\Enums\User\DateFormat and
    // the useDateFormat composable. Null/undefined falls back to ISO.
    date_format?: string | null;
    // Preferred UI language key (e.g. `en`, `ar`). Null/undefined follows the
    // browser locale — see resources/js/i18n and the language switcher.
    locale?: string | null;
    // When the user finished or skipped the first-run welcome tour. Null means
    // not yet onboarded — the dashboard shows the welcome popup. See
    // resources/js/components/onboarding and CompleteOnboardingController.
    onboarded_at?: string | null;
    capabilities?: App.Billing.Data.Resources.Package.BillingPackageCapabilitiesData;
}

export type BreadcrumbItemType = BreadcrumbItem;
