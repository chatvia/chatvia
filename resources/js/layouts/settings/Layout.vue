<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Code, KeyRound, Lock, Palette, Plug, User, type LucideIcon } from 'lucide-vue-next';

interface NavSection {
    title: string;
    items: { title: string; href: string; icon: LucideIcon }[];
}

const sections: NavSection[] = [
    {
        title: 'Account',
        items: [
            { title: 'Profile', href: '/settings/profile', icon: User },
            { title: 'Password', href: '/settings/password', icon: Lock },
            { title: 'API keys', href: '/settings/api-keys', icon: KeyRound },
        ],
    },
    {
        title: 'Integrations',
        items: [
            { title: 'Connectors', href: '/settings/connectors', icon: Plug },
            { title: 'Functions', href: '/settings/functions', icon: Code },
        ],
    },
    {
        title: 'Preferences',
        items: [{ title: 'Appearance', href: '/settings/appearance', icon: Palette }],
    },
];

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
            <aside class="w-full lg:sticky lg:top-32 lg:w-56 lg:max-h-[calc(100vh-9rem)] lg:overflow-y-auto">
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
                                isActive(item.href)
                                    ? 'bg-accent text-accent-foreground font-medium'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                        >
                            <component
                                :is="item.icon"
                                :class="['h-4 w-4 shrink-0', isActive(item.href) ? 'text-accent-foreground' : 'text-muted-foreground group-hover:text-foreground']"
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
