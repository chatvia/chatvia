import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

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

export interface CurrentRoute {
    name: string | null;
    params: Record<string, string>;
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
}

export interface SharedData extends PageProps {
    name: string;
    brand?: Brand;
    quote: { message: string; author: string };
    auth: Auth;
    // The matched route's name and raw parameters, shared by the server for
    // active-state checks. Read them through `useCurrentRoute()`; build URLs
    // with the Wayfinder functions in `@/routes`.
    route: CurrentRoute;
    sidebarOpen: boolean;
    tenant: string;
    permissions: Record<number, string[]>;
    currentTeam: CurrentTeam | null;
    // Persisted UI language for the signed-in user (`auto` follows the browser).
    locale?: string;
    // Install-wide switches for unfinished features (`chatvia.features`), keyed
    // by name (e.g. `youtube_sources`). Read them through `useFeature()`.
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
    // Mirrors App\Identity\Workspace\Enums\UserType. `workspace` = account owner,
    // `employee` = invited teammate/collaborator, `chatter` = widget end-user,
    // `admin` = internal Chatvia staff.
    type: 'admin' | 'workspace' | 'employee' | 'chatter';
    // Preferred date display format key — see App\Identity\Workspace\Enums\DateFormat and
    // the useDateFormat composable. Null/undefined falls back to ISO.
    date_format?: string | null;
    // Preferred UI language key (e.g. `en`, `ar`). Null/undefined follows the
    // browser locale — see resources/js/i18n and the language switcher.
    locale?: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
