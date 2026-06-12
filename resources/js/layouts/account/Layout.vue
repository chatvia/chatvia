<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { Link } from '@inertiajs/vue3';
import { Download, Fingerprint, Lock, Share2, Smartphone, Trash2, User, type LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface NavItem {
    // i18n key resolved at render time (see account.nav.* in the locale files).
    title: string;
    // Ziggy route name — href + active state are both derived from it so a URL
    // change can never desync the nav from the routes.
    name: string;
    icon: LucideIcon;
    // Account-wide actions only the workspace owner may perform — hidden from
    // employees/teammates even when they can reach the account area.
    ownerOnly?: boolean;
    variant?: 'default' | 'danger';
}

const { isOwner } = useTeamPermissions();

const allItems: NavItem[] = [
    { title: 'account.nav.profile', name: 'account.profile.edit', icon: User },
    { title: 'account.nav.password', name: 'account.password.edit', icon: Lock },
    { title: 'account.nav.two_factor', name: 'account.two-factor.edit', icon: Smartphone },
    { title: 'account.nav.passkeys', name: 'account.passkeys.edit', icon: Fingerprint },
    { title: 'account.nav.social', name: 'account.social.edit', icon: Share2 },
    { title: 'account.nav.data_export', name: 'account.data-export.edit', icon: Download, ownerOnly: true },
    { title: 'account.nav.delete', name: 'account.delete', icon: Trash2, variant: 'danger', ownerOnly: true },
];

const items = computed<NavItem[]>(() => allItems.filter((item) => !item.ownerOnly || isOwner.value));

withDefaults(
    defineProps<{
        // 'narrow' (default) matches the Profile/Password forms (max-w-xl).
        // 'wide' opens the column for data-heavy pages.
        width?: 'narrow' | 'wide';
    }>(),
    { width: 'narrow' },
);

function isActive(name: string): boolean {
    return route().current(name);
}
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('account.layout.heading')" :description="t('account.layout.description')" />

        <div class="flex flex-col items-start space-y-8 md:space-y-0 lg:flex-row lg:space-y-0 lg:gap-12">
            <aside class="w-full lg:sticky lg:top-32 lg:w-56">
                <nav class="flex flex-col gap-1">
                    <Link
                        v-for="item in items"
                        :key="item.name"
                        :href="route(item.name)"
                        :class="[
                            'group inline-flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                            item.variant === 'danger'
                                ? isActive(item.name)
                                    ? 'bg-red-50 font-medium text-red-600 dark:bg-red-950/30 dark:text-red-400'
                                    : 'text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/30'
                                : isActive(item.name)
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
                                    : isActive(item.name)
                                      ? 'text-accent-foreground'
                                      : 'text-muted-foreground group-hover:text-foreground',
                            ]"
                        />
                        {{ t(item.title) }}
                    </Link>
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
