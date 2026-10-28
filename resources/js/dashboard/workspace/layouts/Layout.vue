<script setup lang="ts">
import Heading from '@/dashboard/components/Heading.vue';
import { useCurrentRoute } from '@/shared/composables/useCurrentRoute';
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import governance from '@/routes/governance';
import integrations from '@/routes/integrations';
import library from '@/routes/library';
import people from '@/routes/people';
import security from '@/routes/security';
import { Link } from '@inertiajs/vue3';
import { Code, KeyRound, Lock, MessageSquareText, Plug, ScrollText, Users, type LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface NavItem {
    // i18n key resolved at render time (see workspace.nav.* in the locale files).
    title: string;
    // Route name, for the active state.
    name: string;
    href: string;
    icon: LucideIcon;
    permission?: string;
    // Extra route names that also mark this item active — the unified Audit item
    // lives on two tab routes.
    activeNames?: string[];
}

interface NavSection {
    // i18n key resolved at render time (see workspace.sections.* in the locale files).
    title: string;
    items: NavItem[];
}

const { hasPermission } = useTeamPermissions();
const { current } = useCurrentRoute();

const baseSections: NavSection[] = [
    {
        title: 'workspace.sections.integrations',
        items: [
            {
                title: 'workspace.nav.connectors',
                name: 'integrations.connectors.edit',
                href: integrations.connectors.edit.url(),
                icon: Plug,
                permission: 'account.connectors',
            },
            {
                title: 'workspace.nav.functions',
                name: 'integrations.functions.edit',
                href: integrations.functions.edit.url(),
                icon: Code,
                permission: 'account.functions',
            },
            {
                title: 'workspace.nav.api_keys',
                name: 'integrations.api-keys.edit',
                href: integrations.apiKeys.edit.url(),
                icon: KeyRound,
                permission: 'account.api_keys',
            },
        ],
    },
    {
        title: 'workspace.sections.library',
        items: [
            {
                title: 'workspace.nav.prompts',
                name: 'library.prompts.edit',
                href: library.prompts.edit.url(),
                icon: MessageSquareText,
                permission: 'account.prompts',
            },
        ],
    },
    {
        title: 'workspace.sections.people',
        items: [
            {
                title: 'workspace.nav.members',
                name: 'people.chatters.index',
                href: people.chatters.index.url(),
                icon: Users,
                permission: 'account.chatters.manage',
            },
        ],
    },
];

// The two workspace audit logs share one "Audit" item (Tool calls + Security
// tabs). It shows with either permission and its href points at the first tab
// the role can open; the network-access policy editor stays a separate item.
const governanceItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (hasPermission('account.governance') || hasPermission('account.security')) {
        const canGovern = hasPermission('account.governance');

        items.push({
            title: 'workspace.nav.audit',
            name: canGovern ? 'governance.audit' : 'security.audit',
            href: canGovern ? governance.audit.url() : security.audit.url(),
            icon: ScrollText,
            activeNames: ['governance.audit', 'security.audit'],
        });
    }

    if (hasPermission('account.security')) {
        items.push({ title: 'workspace.nav.security', name: 'security.edit', href: security.edit.url(), icon: Lock });
    }

    return items;
});

// Hide items the team role can't reach, then drop any section left empty.
const sections = computed<NavSection[]>(() =>
    [...baseSections, { title: 'workspace.sections.governance', items: governanceItems.value }]
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => !item.permission || hasPermission(item.permission)),
        }))
        .filter((section) => section.items.length > 0),
);

withDefaults(
    defineProps<{
        width?: 'narrow' | 'wide';
    }>(),
    { width: 'wide' },
);

function isActive(item: NavItem): boolean {
    return (item.activeNames ?? [item.name]).some((name) => current(name));
}
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('workspace.layout.heading')" :description="t('workspace.layout.description')" />

        <div class="flex flex-col items-start space-y-8 md:space-y-0 lg:flex-row lg:gap-12 lg:space-y-0">
            <aside class="w-full lg:sticky lg:top-32 lg:w-56">
                <nav class="flex flex-col gap-6">
                    <div v-for="section in sections" :key="section.title" class="flex flex-col gap-1">
                        <p class="text-muted-foreground px-3 py-1 text-xs font-medium tracking-wide uppercase">
                            {{ t(section.title) }}
                        </p>
                        <Link
                            v-for="item in section.items"
                            :key="item.name"
                            :href="item.href"
                            :class="[
                                'group inline-flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                isActive(item)
                                    ? 'bg-accent text-accent-foreground font-medium'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'h-4 w-4 shrink-0',
                                    isActive(item) ? 'text-accent-foreground' : 'text-muted-foreground group-hover:text-foreground',
                                ]"
                            />
                            {{ t(item.title) }}
                        </Link>
                    </div>
                </nav>
            </aside>

            <div :class="['flex-1', width === 'wide' ? 'md:max-w-6xl' : 'md:max-w-2xl']">
                <section :class="['space-y-12', width === 'wide' ? '' : 'max-w-xl']">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
