<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { Link } from '@inertiajs/vue3';
import { Code, KeyRound, MessageSquareText, Plug, ShieldCheck, Users, type LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface NavItem {
    // i18n key resolved at render time (see workspace.nav.* in the locale files).
    title: string;
    // Ziggy route name — href + active state derive from it (see account layout).
    name: string;
    icon: LucideIcon;
    permission?: string;
}

interface NavSection {
    // i18n key resolved at render time (see workspace.sections.* in the locale files).
    title: string;
    items: NavItem[];
}

const { hasPermission } = useTeamPermissions();

const allSections: NavSection[] = [
    {
        title: 'workspace.sections.integrations',
        items: [
            { title: 'workspace.nav.connectors', name: 'integrations.connectors.edit', icon: Plug, permission: 'account.connectors' },
            { title: 'workspace.nav.functions', name: 'integrations.functions.edit', icon: Code, permission: 'account.functions' },
            { title: 'workspace.nav.api_keys', name: 'integrations.api-keys.edit', icon: KeyRound, permission: 'account.api_keys' },
        ],
    },
    {
        title: 'workspace.sections.library',
        items: [{ title: 'workspace.nav.prompts', name: 'library.prompts.edit', icon: MessageSquareText, permission: 'account.prompts' }],
    },
    {
        title: 'workspace.sections.people',
        items: [{ title: 'workspace.nav.members', name: 'people.members.index', icon: Users, permission: 'account.members.manage' }],
    },
    {
        title: 'workspace.sections.governance',
        items: [{ title: 'workspace.nav.audit_log', name: 'governance.audit', icon: ShieldCheck, permission: 'account.governance' }],
    },
];

// Hide items the team role can't reach, then drop any section left empty.
const sections = computed<NavSection[]>(() =>
    allSections
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

function isActive(name: string): boolean {
    return route().current(name);
}
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('workspace.layout.heading')" :description="t('workspace.layout.description')" />

        <div class="flex flex-col items-start space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:gap-12">
            <aside class="w-full lg:sticky lg:top-32 lg:w-56">
                <nav class="flex flex-col gap-6">
                    <div v-for="section in sections" :key="section.title" class="flex flex-col gap-1">
                        <p class="text-muted-foreground px-3 py-1 text-xs font-medium tracking-wide uppercase">
                            {{ t(section.title) }}
                        </p>
                        <Link
                            v-for="item in section.items"
                            :key="item.name"
                            :href="route(item.name)"
                            :class="[
                                'group inline-flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                isActive(item.name)
                                    ? 'bg-accent text-accent-foreground font-medium'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'h-4 w-4 shrink-0',
                                    isActive(item.name) ? 'text-accent-foreground' : 'text-muted-foreground group-hover:text-foreground',
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
