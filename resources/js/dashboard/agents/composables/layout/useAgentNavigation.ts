/**
 * The agent sidebar's navigation, filtered by the current user's team
 * permissions: the top items (Playground, Setup, Deploy), the labelled
 * management groups (Insights, Training, Access, Settings) and the owner-only
 * Danger Zone. A group the user can do nothing in is left out entirely.
 */
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import agents from '@/routes/agents';
import {
    BarChart3,
    BotIcon,
    Code,
    Database,
    FileText,
    Fingerprint,
    FlaskConical,
    FunctionSquare,
    Gauge,
    Heart,
    MessageSquare,
    Pencil,
    Plug,
    Rocket,
    Send,
    ShieldAlert,
    ShieldCheck,
    SmilePlus,
    Sparkles,
    Trash2,
    Users,
    UserX,
} from 'lucide-vue-next';
import { type Component, computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface AgentNavBase {
    id: string;
    label: string;
    icon: Component;
    tooltip: string;
}

/** The URL of one of the agent's pages, given the agent's external id. */
export type AgentPageUrl = (agent: string) => string;

/** A link inside a collapsible submenu. */
export interface AgentNavSubItem {
    id: string;
    label: string;
    icon: Component;
    /** Route name (or `*` pattern), only for the active state. */
    route: string;
    url: AgentPageUrl;
}

/** A plain link to one of the agent's pages. */
export interface AgentNavLinkItem extends AgentNavBase {
    kind: 'link';
    /** Route name (or `*` pattern), only for the active state. */
    route: string;
    url: AgentPageUrl;
}

/** A collapsible submenu (Analytics, Security) with its own links. */
export interface AgentNavSubmenuItem extends AgentNavBase {
    kind: 'submenu';
    subItems: AgentNavSubItem[];
}

/** The Knowledge Sources submenu; its links come from AgentSourcesMenu. */
export interface AgentNavSourcesItem extends AgentNavBase {
    kind: 'sources';
}

export type AgentNavAction = 'rename' | 'delete';

/** A button that opens a dialog instead of navigating. */
export interface AgentNavActionItem extends AgentNavBase {
    kind: 'action';
    action: AgentNavAction;
}

export type AgentNavItem = AgentNavLinkItem | AgentNavSubmenuItem | AgentNavSourcesItem | AgentNavActionItem;

export interface AgentNavGroup {
    id: string;
    label: string;
    items: AgentNavItem[];
}

const SOURCE_PERMISSIONS = [
    'agent.sources.train.files',
    'agent.sources.train.text',
    'agent.sources.train.qa',
    'agent.sources.train.web',
    'agent.sources.train.audio',
    'agent.sources.train.video',
];

function link(id: string, label: string, icon: Component, route: string, url: AgentPageUrl, tooltip: string): AgentNavLinkItem {
    return { kind: 'link', id, label, icon, route, url, tooltip };
}

function subLink(id: string, label: string, icon: Component, route: string, url: AgentPageUrl): AgentNavSubItem {
    return { id, label, icon, route, url };
}

export function useAgentNavigation() {
    const { t } = useI18n();
    const { hasPermission, hasAnyPermission, isOwner } = useTeamPermissions();

    // Top entries: Playground (read-only preview), Setup (one configuration page
    // with a Hosted Chat / Widget / Bar preview switcher) and Deploy.
    // Capabilities, Forms and Channels live in the management groups below to
    // keep this section short.
    const topItems = computed<AgentNavLinkItem[]>(() => {
        if (!hasPermission('agent.setup')) return [];

        return [
            link('playground', t('agents.nav.playground'), BotIcon, 'agents.playground', agents.playground.url, t('agents.nav.playground_tooltip')),
            link('setup', t('agents.nav.setup'), Code, 'agents.setup', agents.setup.url, t('agents.nav.setup_tooltip')),
            link('deploy', t('agents.nav.deploy'), Rocket, 'agents.deploy', agents.deploy.url, t('agents.nav.deploy_tooltip')),
        ];
    });

    // Insights — analytics, raw chat logs, audit, evals and guardrails.
    // Captured contacts live under Forms (a form → Submissions).
    function insightsItems(): AgentNavItem[] {
        const items: AgentNavItem[] = [];

        const analytics: AgentNavSubItem[] = [];
        if (hasPermission('agent.analytics.chats')) {
            analytics.push(subLink('analytics-overview', t('agents.nav.chats'), BarChart3, 'agents.analytics', agents.analytics.url));
        }
        if (hasPermission('agent.analytics.sentiment')) {
            analytics.push(
                subLink('analytics-sentiment', t('agents.nav.sentiment'), SmilePlus, 'agents.analytics.sentiment', agents.analytics.sentiment.url),
            );
        }
        if (hasPermission('agent.analytics.tone')) {
            analytics.push(subLink('analytics-tone', t('agents.nav.tone'), Heart, 'agents.analytics.tone', agents.analytics.tone.url));
        }
        if (hasPermission('agent.analytics.topics')) {
            analytics.push(
                subLink('analytics-topics', t('agents.nav.topics'), MessageSquare, 'agents.analytics.topics', agents.analytics.topics.url),
            );
        }
        if (hasPermission('agent.analytics.sources')) {
            analytics.push(subLink('analytics-sources', t('agents.nav.sources'), FileText, 'agents.analytics.sources', agents.analytics.sources.url));
        }
        if (analytics.length > 0) {
            items.push({
                kind: 'submenu',
                id: 'analytics',
                label: t('agents.nav.analytics'),
                icon: BarChart3,
                tooltip: t('agents.nav.analytics_tooltip'),
                subItems: analytics,
            });
        }

        if (hasPermission('agent.chat.view')) {
            items.push(link('logs', t('agents.nav.chat'), MessageSquare, 'agents.logs', agents.logs.url, t('agents.nav.chat_tooltip')));
        }
        if (hasPermission('agent.audit.view')) {
            items.push(link('audit', t('agents.nav.audit'), ShieldCheck, 'agents.audit', agents.audit.url, t('agents.nav.audit_tooltip')));
        }
        if (hasPermission('agent.evals.view')) {
            items.push(link('evals', t('agents.nav.evals'), FlaskConical, 'agents.evals', agents.evals.url, t('agents.nav.evals_tooltip')));
        }
        if (hasPermission('agent.guardrails.view')) {
            items.push(
                link(
                    'guardrails',
                    t('agents.nav.guardrails'),
                    ShieldAlert,
                    'agents.guardrails',
                    agents.guardrails.url,
                    t('agents.nav.guardrails_tooltip'),
                ),
            );
        }

        return items;
    }

    // Training — knowledge sources and the tools the agent can call.
    function trainingItems(): AgentNavItem[] {
        const items: AgentNavItem[] = [];

        if (hasAnyPermission(SOURCE_PERMISSIONS)) {
            items.push({
                kind: 'sources',
                id: 'knowledge',
                label: t('agents.nav.knowledge_sources'),
                icon: Database,
                tooltip: t('agents.nav.knowledge_sources_tooltip'),
            });
        }
        if (hasPermission('agent.sources.train.functions')) {
            items.push(
                link(
                    'functions',
                    t('agents.nav.functions'),
                    FunctionSquare,
                    'agents.sources.functions.index',
                    agents.sources.functions.index.url,
                    t('agents.nav.functions_tooltip'),
                ),
            );
        }
        // Capabilities (first-party chat features such as Interactive Charts)
        // and Forms share the connector attach/detach permission.
        if (hasPermission('agent.sources.train.connectors')) {
            items.push(
                link(
                    'connectors',
                    t('agents.nav.connectors'),
                    Plug,
                    'agents.sources.connectors.index',
                    agents.sources.connectors.index.url,
                    t('agents.nav.connectors_tooltip'),
                ),
                link(
                    'capabilities',
                    t('agents.nav.capabilities'),
                    Sparkles,
                    'agents.capabilities',
                    agents.capabilities.url,
                    t('agents.nav.capabilities_tooltip'),
                ),
                link('forms', t('agents.nav.forms'), FileText, 'agents.forms.index', agents.forms.index.url, t('agents.nav.forms_tooltip')),
            );
        }

        return items;
    }

    // Access — who can chat with the agent, where they reach it, and how they
    // prove who they are.
    function accessItems(): AgentNavItem[] {
        const items: AgentNavItem[] = [];

        if (hasPermission('agent.chatters.manage')) {
            items.push(
                link('members', t('agents.nav.members'), Users, 'agents.chatters.index', agents.chatters.index.url, t('agents.nav.members_tooltip')),
            );
        }
        // Channels has its own permission (mirrors the `agent.permission:agent.channels`
        // route gate): a teammate can manage channels without the broader setup grant.
        if (hasPermission('agent.channels')) {
            items.push(link('channels', t('agents.nav.channels'), Send, 'agents.channels', agents.channels.url, t('agents.nav.channels_tooltip')));
        }
        // Security: identity and rate limits need the identity grant; the
        // Blocked list follows the Guardrails page, where people are blocked.
        const security: AgentNavSubItem[] = [];
        if (hasPermission('agent.identity.manage')) {
            security.push(
                subLink('security-identity', t('agents.nav.identity'), Fingerprint, 'agents.identity', agents.identity.url),
                subLink('security-rate-limit', t('agents.nav.rate_limiting'), Gauge, 'agents.security.rate-limit', agents.security.rateLimit.url),
            );
        }
        if (hasPermission('agent.guardrails.view')) {
            security.push(subLink('security-blocked', t('agents.nav.blocked'), UserX, 'agents.security.blocked', agents.security.blocked.url));
        }
        if (security.length) {
            items.push({
                kind: 'submenu',
                id: 'security',
                label: t('agents.nav.security'),
                icon: ShieldCheck,
                tooltip: t('agents.nav.security_tooltip'),
                subItems: security,
            });
        }

        return items;
    }

    // Settings — agent metadata (rename), owners only.
    function settingsItems(): AgentNavItem[] {
        if (!isOwner.value) return [];

        return [
            { kind: 'action', id: 'rename', label: t('agents.nav.rename'), icon: Pencil, action: 'rename', tooltip: t('agents.nav.rename_tooltip') },
        ];
    }

    const managementGroups = computed<AgentNavGroup[]>(() =>
        [
            { id: 'insights', label: t('agents.nav.groups.insights'), items: insightsItems() },
            { id: 'training', label: t('agents.nav.groups.training'), items: trainingItems() },
            { id: 'access', label: t('agents.nav.groups.access'), items: accessItems() },
            { id: 'settings', label: t('agents.nav.groups.settings'), items: settingsItems() },
        ].filter((group) => group.items.length > 0),
    );

    // Danger Zone — deleting the agent, for whoever holds `agent.delete` (as the route).
    const dangerZoneItems = computed<AgentNavActionItem[]>(() => {
        if (!hasPermission('agent.delete')) return [];

        return [
            {
                kind: 'action',
                id: 'delete',
                label: t('agents.nav.delete_agent'),
                icon: Trash2,
                action: 'delete',
                tooltip: t('agents.nav.delete_agent_tooltip'),
            },
        ];
    });

    return { topItems, managementGroups, dangerZoneItems };
}
