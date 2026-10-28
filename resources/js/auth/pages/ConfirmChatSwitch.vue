<script setup lang="ts">
// "Continue as …?" on an agent's hosted chat: a sign-in link was opened in a
// browser already signed in to the chat as someone else. Nothing changes until
// the person confirms; "stay" drops the link and keeps the current account.
import ThemeSwitcher from '@/shared/components/ThemeSwitcher.vue';
import { Button } from '@/shared/ui/button';
import { useColorTheme } from '@/shared/composables/useColorTheme';
import AuthLayout from '@/shared/layouts/AuthLayout.vue';
import { destroy as staySignedIn, store as switchAccount } from '@/routes/chat/switch-account';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, UserRound } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    agentName?: string;
    appearance?: {
        color_theme?: string;
    };
    currentName?: string | null;
    nextName: string;
    nextEmail?: string | null;
}>();

const form = useForm({});

const { applyColorTheme } = useColorTheme();

const confirm = () => {
    form.post(switchAccount.url());
};

const stay = () => {
    form.delete(staySignedIn.url());
};

onMounted(() => {
    applyColorTheme(props.appearance);
});
</script>

<template>
    <AuthLayout :title="agentName ?? ''" :description="t('auth.switch_chat_account.description')">
        <Head :title="t('auth.switch_chat_account.head')" />

        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <div class="mx-auto w-full max-w-lg space-y-6">
            <div class="flex items-center gap-3 rounded-lg border p-4">
                <UserRound class="text-muted-foreground h-6 w-6 shrink-0" />
                <div class="min-w-0">
                    <p class="truncate font-medium">{{ nextName }}</p>
                    <p v-if="nextEmail" class="text-muted-foreground truncate text-sm">{{ nextEmail }}</p>
                </div>
            </div>

            <p v-if="currentName" class="text-muted-foreground text-sm">
                {{ t('auth.switch_chat_account.signed_in_as', { name: currentName }) }}
            </p>

            <div class="flex flex-col gap-3">
                <Button type="button" class="h-12 w-full text-base font-semibold" :disabled="form.processing" @click="confirm">
                    <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                    {{ t('auth.switch_chat_account.continue_as', { name: nextName }) }}
                </Button>
                <Button type="button" variant="outline" class="h-12 w-full text-base" :disabled="form.processing" @click="stay">
                    {{ currentName ? t('auth.switch_chat_account.stay_as', { name: currentName }) : t('auth.switch_chat_account.cancel') }}
                </Button>
            </div>
        </div>
    </AuthLayout>
</template>
