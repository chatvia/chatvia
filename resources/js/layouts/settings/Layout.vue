<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Code,
    Download,
    Fingerprint,
    KeyRound,
    Lock,
    MessageSquareText,
    Palette,
    Plug,
    Share2,
    Smartphone,
    Trash2,
    User,
    Users,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface NavItem {
    title: string;
    href: string;
    icon: LucideIcon;
    permission?: string;
    variant?: 'default' | 'danger';
}

interface NavSection {
    title: string;
    items: NavItem[];
}

const { hasPermission } = useTeamPermissions();

const allSections: NavSection[] = [
    {
        title: 'Account',
        items: [
            { title: 'Profile', href: '/settings/profile', icon: User },
            { title: 'Password', href: '/settings/password', icon: Lock },
            { title: 'Two-factor', href: '/settings/two-factor', icon: Smartphone },
            { title: 'Passkeys', href: '/settings/passkeys', icon: Fingerprint },
            { title: 'Social accounts', href: '/settings/social', icon: Share2 },
            { title: 'Download my data', href: '/settings/data-export', icon: Download },
            { title: 'Delete account', href: '/settings/delete-account', icon: Trash2, variant: 'danger' },
        ],
    },
    {
        title: 'Integrations',
        items: [
            { title: 'Connectors', href: '/settings/connectors', icon: Plug, permission: 'account.connectors' },
            { title: 'Functions', href: '/settings/functions', icon: Code, permission: 'account.functions' },
            { title: 'API keys', href: '/settings/api-keys', icon: KeyRound, permission: 'account.api_keys' },
        ],
    },
    {
        title: 'Library',
        items: [{ title: 'Prompts', href: '/settings/prompts', icon: MessageSquareText, permission: 'account.prompts' }],
    },
    {
        title: 'People',
        items: [{ title: 'Members', href: '/settings/members', icon: Users, permission: 'account.members.manage' }],
    },
    {
        title: 'Preferences',
        items: [{ title: 'Appearance', href: '/settings/appearance', icon: Palette }],
    },
];

const sections = computed<NavSection[]>(() =>
    allSections
        .map((section) => ({
            ...section,
            items: section.items.filter((item) => !item.permission || hasPermission(item.permission)),
        }))
        .filter((section) => section.items.length > 0),
);

const props = withDefaults(
    defineProps<{
        // 'narrow' is the default and matches the original Profile/Password
        // forms (max-w-xl). 'wide' opens the content area for data-heavy pages
        // like Connectors / Functions / API keys where a 576px column clips
        // tables.
        width?: 'narrow' | 'wide';
    }>(),
    { width: 'narrow' },
);

const page = usePage<SharedData>();

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';

function isActive(href: string): boolean {
    return currentPath === href || currentPath.startsWith(href + '/');
}
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your profile and account settings" />

        <div class="flex flex-col items-start space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:space-x-12">
            <aside class="w-full lg:sticky lg:top-32 lg:max-h-[calc(100vh-9rem)] lg:w-56 lg:overflow-y-auto">
                <nav class="flex flex-col gap-6">
                    <div v-for="section in sections" :key="section.title" class="flex flex-col gap-1">
                        <p class="text-muted-foreground px-3 text-xs font-medium tracking-wide uppercase">
                            {{ section.title }}
                        </p>
                        <Link
                            v-for="item in section.items"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'group inline-flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                item.variant === 'danger'
                                    ? isActive(item.href)
                                        ? 'bg-red-50 font-medium text-red-600 dark:bg-red-950/30 dark:text-red-400'
                                        : 'text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/30'
                                    : isActive(item.href)
                                      ? 'bg-accent text-accent-foreground font-medium'
                                      : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="[
                                    'h-4 w-4 shrink-0',
                                    item.variant === 'danger'
                                        ? 'text-red-600 dark:text-red-400'
                                        : isActive(item.href)
                                          ? 'text-accent-foreground'
                                          : 'text-muted-foreground group-hover:text-foreground',
                                ]"
                            />
                            {{ item.title }}
                        </Link>
                    </div>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div :class="['flex-1', props.width === 'wide' ? 'md:max-w-6xl' : 'md:max-w-2xl']">
                <section :class="['space-y-12', props.width === 'wide' ? '' : 'max-w-xl']">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
