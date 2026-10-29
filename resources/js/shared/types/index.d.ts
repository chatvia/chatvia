import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    // Dashboard only: true for the owner of the signed-in user's workspace,
    // false for a teammate. Owner-only areas are hidden from teammates.
    is_workspace_owner?: boolean;
    // Hosted chat only: true when the signed-in viewer is a chatter (a
    // workspace end-user), false for staff opening the chat from the dashboard.
    is_chatter?: boolean;
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
    // A count drawn on the link (e.g. chats waiting in the Inbox); hidden at 0.
    badge?: number;
    // The `data-tour` name of the link, for the onboarding tour's stops.
    tour?: string;
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

export interface SharedData extends PageProps {
    name: string;
    // The install's own icon (Settings → Platform, licensed installs only),
    // shown on the sign-in pages in place of the ChatVia.ai mark; null for the mark.
    logo: string | null;
    // Whether the "Powered by ChatVia.ai" credit shows under the hosted chat
    // (the admin panel's License page). Shared on hosted chat pages only.
    poweredBy?: boolean;
    // The Cloudflare Turnstile bot check of the public forms (Settings →
    // Security), or null while it is not set up. Read through useBotCheck().
    turnstile: { site_key: string; forms: string[] } | null;
    // The sign-in providers whose OAuth app is set up (Settings → Sign-in).
    // Read through useSocialProviders().
    socialProviders?: string[];
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
    // The notification bell: unread count and the person's switches for
    // desktop alerts and sound (null when nobody is signed in). Read through
    // useNotificationCenter(); live updates arrive over Reverb.
    notifications: Domain.Notification.Data.NotificationSummaryData | null;
    // Persisted UI language for the signed-in user (`auto` follows the browser).
    locale?: string;
    // The install's default language (Settings → Platform), for `auto` when
    // the browser's language is not supported.
    defaultLocale?: string;
    // Whether the owner may download their data and delete their account in
    // the Account area (admin Settings → Workspaces → Owner account). Null
    // when nobody is signed in.
    accountOptions: { data_export: boolean; deletion: boolean } | null;
    // The dashboard tour: how far this person got (`stage`, null = not
    // started), or null while the admin has it off (Settings → Workspaces →
    // Onboarding). Read through useOnboardingTour().
    onboardingTour: { stage: App.Identity.Workspace.Enums.OnboardingTourStage | null } | null;
    // Whether this install has an Enterprise key: Security and Governance
    // unlock their Enterprise features with it.
    enterprise: boolean;
    // Whether this install holds any license key, and the free-use limit that
    // applies without one (null = no limit). Null when nobody is signed in.
    // Read through useLicense().
    license: { licensed: boolean; limits: { workspaces: number | null } } | null;
    // The knowledge file types this install accepts (admin Settings →
    // Ingestion), each with its extensions. Read through useKnowledgeFileTypes().
    knowledgeFileTypes: Partial<Record<Domain.Knowledge.Source.File.Enums.KnowledgeFileType, string[]>>;
    // Whether websites can be imported as knowledge (Settings → Knowledge →
    // Web scraper is set up).
    canImportWebsites?: boolean;

    // Where every agent's hosted chat lives (`{chatBaseUrl}/{slug}`).
    chatBaseUrl: string;
    // On a hosted chat page: its `/chat/{agentSlug}` segment (null elsewhere).
    agentSlug: string | null;
    // The session's CSRF token, sent as X-CSRF-TOKEN (utils/csrf.ts).
    csrfToken: string;

    // Chat-flow / widget contexts share the resolved agent and surface flash
    // messages and the request schema. Most pages don't see these but typing
    // them here keeps `$page.props.X` honest.
    agent?: {
        id: number;
        external_id: string;
        name: string;
        [key: string]: unknown;
    };
    schema?: string;
    environment?: string;
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
    // Platform staff: may open the admin panel. Whether the user owns their
    // workspace is `auth.is_workspace_owner`.
    is_admin?: boolean;
    // Preferred date display format, a PHP date() string — see App\Identity\Workspace\Enums\DateFormat
    // and the useDateFormat composable. Null/undefined falls back to ISO.
    date_format?: string | null;
    // Preferred UI language key (e.g. `en`, `ar`). Null/undefined follows the
    // browser locale — see resources/js/i18n and the language switcher.
    locale?: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
