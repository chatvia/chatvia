<script setup lang="ts">
// Shell of the Account area: the side navigation (owner-only entries are
// hidden from teammates) and a narrow or wide content column.
import Heading from '@/components/Heading.vue';
import { useCurrentRoute } from '@/composables/useCurrentRoute';
import { useSocialProviders } from '@/composables/useSocialProviders';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import account from '@/routes/account';
import { Link } from '@inertiajs/vue3';
import { Bell, Download, Fingerprint, Lock, Share2, Smartphone, Trash2, User, type LucideIcon } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface NavItem {
    // i18n key resolved at render time (see account.nav.* in the locale files).
    title: string;
    // Route name, for the active state.
    name: string;
    href: string;
    icon: LucideIcon;
    // Account-wide actions only the workspace owner may perform — hidden from
    // employees/teammates even when they can reach the account area.
    ownerOnly?: boolean;
    // Only when the admin has set up at least one sign-in provider
    // (Settings → Sign-in); with none there is nothing to connect.
    needsSocialProvider?: boolean;
    variant?: 'default' | 'danger';
}

const { isOwner } = useTeamPermissions();
const { current } = useCurrentRoute();
const { hasAny: hasAnySocialProvider } = useSocialProviders();

const allItems: NavItem[] = [
    { title: 'account.nav.profile', name: 'account.profile.edit', href: account.profile.edit.url(), icon: User },
    { title: 'account.nav.notifications', name: 'account.notifications.edit', href: account.notifications.edit.url(), icon: Bell },
    { title: 'account.nav.password', name: 'account.password.edit', href: account.password.edit.url(), icon: Lock },
    { title: 'account.nav.two_factor', name: 'account.two-factor.edit', href: account.twoFactor.edit.url(), icon: Smartphone },
    { title: 'account.nav.passkeys', name: 'account.passkeys.edit', href: account.passkeys.edit.url(), icon: Fingerprint },
    { title: 'account.nav.social', name: 'account.social.edit', href: account.social.edit.url(), icon: Share2, needsSocialProvider: true },
    { title: 'account.nav.data_export', name: 'account.data-export.edit', href: account.dataExport.edit.url(), icon: Download, ownerOnly: true },
    { title: 'account.nav.delete', name: 'account.delete', href: account.delete.url(), icon: Trash2, variant: 'danger', ownerOnly: true },
];

const items = computed<NavItem[]>(() =>
    allItems.filter(
        (item) =>
            (!item.ownerOnly || isOwner.value) && (!item.needsSocialProvider || hasAnySocialProvider(['google', 'microsoft', 'slack', 'github'])),
    ),
);

withDefaults(
    defineProps<{
        // 'narrow' (default) matches the Profile/Password forms (max-w-xl).
        // 'wide' opens the column for data-heavy pages.
        width?: 'narrow' | 'wide';
    }>(),
    { width: 'narrow' },
);

function isActive(name: string): boolean {
    return current(name);
}
</script>

<template>
    <div class="px-4 py-6">
        <Heading :title="t('account.layout.heading')" :description="t('account.layout.description')" />

        <div class="flex flex-col items-start space-y-8 md:space-y-0 lg:flex-row lg:gap-12 lg:space-y-0">
            <aside class="w-full lg:sticky lg:top-32 lg:w-56">
                <nav class="flex flex-col gap-1">
                    <Link
                        v-for="item in items"
                        :key="item.name"
                        :href="item.href"
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
