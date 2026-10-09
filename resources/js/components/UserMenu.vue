<script setup lang="ts">
// Avatar button in the app header that opens the account menu.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { Auth } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const auth = computed(() => page.props.auth as Auth);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="focus-within:ring-primary relative size-10 w-auto rounded-full p-1 focus-within:ring-2"
                :aria-label="t('nav.open_user_menu')"
            >
                <Avatar class="size-8 overflow-hidden rounded-full">
                    <AvatarImage v-if="auth.user.avatar" :src="auth.user.avatar" :alt="auth.user.name" />
                    <AvatarFallback class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                        {{ getInitials(auth.user?.name) }}
                    </AvatarFallback>
                </Avatar>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-56">
            <UserMenuContent :user="auth.user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
