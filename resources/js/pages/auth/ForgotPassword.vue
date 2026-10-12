<script setup lang="ts">
import TurnstileWidget from '@/components/auth/TurnstileWidget.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBotCheck } from '@/composables/useBotCheck';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email as sendResetLink } from '@/routes/password';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    status?: string;
}>();

const { siteKey: botCheckKey } = useBotCheck('password_reset');
const botCheck = ref<InstanceType<typeof TurnstileWidget> | null>(null);

const form = useForm({
    email: '',
    'cf-turnstile-response': '',
});

// A Turnstile token counts once, so every submit needs a new one.
const submit = () => {
    form.post(sendResetLink.url(), {
        onFinish: () => botCheck.value?.reset(),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.forgot_password.title')" :description="t('auth.forgot_password.description')">
        <Head :title="t('auth.forgot_password.head')" />

        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Status ChatMessage -->
            <div v-if="status" class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <!-- Forgot Password Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">{{ t('auth.forgot_password.email_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="form.email"
                            autofocus
                            :placeholder="t('auth.forgot_password.email_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <div v-if="botCheckKey" class="space-y-2">
                    <TurnstileWidget ref="botCheck" v-model="form['cf-turnstile-response']" :site-key="botCheckKey" />
                    <InputError :message="form.errors['cf-turnstile-response']" />
                </div>

                <!-- Send Reset Link Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">{{ t('auth.forgot_password.send_reset_link') }}</span>
                    <span v-else>{{ t('auth.forgot_password.sending_reset_link') }}</span>
                </Button>
            </form>

            <!-- Sign In Link -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    {{ t('auth.forgot_password.return_to') }}
                    <TextLink :href="login.url()" class="text-primary hover:text-primary/80 ms-1 font-semibold">
                        {{ t('auth.forgot_password.log_in') }}
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthLayout>
</template>
