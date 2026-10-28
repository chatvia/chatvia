<script setup lang="ts">
// Contents of the avatar dropdown: who is signed in, links to the account and
// workspace areas, "Take the tour" (while the admin has the tour on), the UI
// language picker, and sign-out.
import { logout } from '@/dashboard/api/account';
import UserInfo from '@/dashboard/components/UserInfo.vue';
import { useOnboardingTour } from '@/dashboard/composables/onboarding/useOnboardingTour';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/shared/ui/dropdown-menu';
import { DEFAULT_DATE_FORMAT } from '@/dashboard/composables/useDateFormat';
import { useTeamPermissions } from '@/dashboard/composables/useTeamPermissions';
import { LOCALES, normalizeLocale, persistLocale, setI18nLocale } from '@/shared/i18n';
import { home } from '@/routes';
import { update as updatePreferences } from '@/routes/account/preferences';
import { edit as editProfile } from '@/routes/account/profile';
import governance from '@/routes/governance';
import integrations from '@/routes/integrations';
import library from '@/routes/library';
import people from '@/routes/people';
import type { User } from '@/shared/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, Check, CircleUser, Compass, Languages, LogOut } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    user: User;
}

defineProps<Props>();

const { t, locale } = useI18n();
const page = usePage();
const { hasPermission } = useTeamPermissions();
const { enabled: tourEnabled, restart: restartTour } = useOnboardingTour();

// Workspace administration is permission-gated. Point the entry at the first
// section the signed-in role can actually reach; hide it entirely if none.
const workspaceHref = computed<string | null>(() => {
    const sections: [permission: string, url: string][] = [
        ['account.connectors', integrations.connectors.edit.url()],
        ['account.functions', integrations.functions.edit.url()],
        ['account.api_keys', integrations.apiKeys.edit.url()],
        ['account.prompts', library.prompts.edit.url()],
        ['account.chatters.manage', people.chatters.index.url()],
        ['account.governance', governance.audit.url()],
    ];

    const match = sections.find(([permission]) => hasPermission(permission));

    return match ? match[1] : null;
});

const activeLocale = computed(() => normalizeLocale(locale.value));

const changeLocale = (code: string): void => {
    if (code === activeLocale.value) {
        return;
    }

    // Instant UI feedback — flips vue-i18n + document dir/lang right away, and
    // mirror it to localStorage so it stays in sync with the guest fallback.
    void setI18nLocale(code);
    persistLocale(code);

    // Persist the choice for the signed-in user. The Date & time preference is
    // required by the endpoint, so echo the current value back unchanged.
    router.patch(
        updatePreferences.url(),
        { locale: code, date_format: page.props.auth.user?.date_format || DEFAULT_DATE_FORMAT },
        { preserveScroll: true, preserveState: true },
    );
};

const handleLogout = () => {
    router.flushAll();
    logout().then(() => {
        window.location.href = home.url();
    });
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="editProfile.url()" prefetch as="button">
                <CircleUser class="me-2 h-4 w-4" />
                {{ t('nav.account') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="workspaceHref" :as-child="true">
            <Link class="block w-full cursor-pointer" :href="workspaceHref" prefetch as="button">
                <Building2 class="me-2 h-4 w-4" />
                {{ t('nav.workspace') }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="tourEnabled" class="cursor-pointer" @select="restartTour">
            <Compass class="me-2 h-4 w-4" />
            {{ t('onboarding.take_tour') }}
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuSub>
        <DropdownMenuSubTrigger>
            <Languages class="me-2 h-4 w-4" />
            {{ t('nav.language') }}
        </DropdownMenuSubTrigger>
        <DropdownMenuSubContent class="max-h-80 overflow-y-auto">
            <DropdownMenuItem v-for="item in LOCALES" :key="item.code" class="cursor-pointer" @select="changeLocale(item.code)">
                <img :src="`/images/flags/${item.flag}.svg`" :alt="item.nativeName" class="me-2 h-4 w-5 shrink-0 rounded-[2px] object-cover" />
                <span class="flex-1">{{ item.nativeName }}</span>
                <Check v-if="item.code === activeLocale" class="ms-2 h-4 w-4" />
            </DropdownMenuItem>
        </DropdownMenuSubContent>
    </DropdownMenuSub>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <a class="block w-full cursor-pointer" @click="handleLogout" as="button">
            <LogOut class="me-2 h-4 w-4" />
            {{ t('nav.logout') }}
        </a>
    </DropdownMenuItem>
</template>
