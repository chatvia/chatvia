<script setup lang="ts">
import TurnstileWidget from '@/auth/components/TurnstileWidget.vue';
import InputError from '@/shared/components/InputError.vue';
import TextLink from '@/auth/components/TextLink.vue';
import ThemeSwitcher from '@/shared/components/ThemeSwitcher.vue';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { useBotCheck } from '@/auth/composables/useBotCheck';
import AuthBase from '@/shared/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store as storeRegistration } from '@/routes/register';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, LockKeyhole, Mail, User } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

// Self-serve sign-up, open only when the install allows it (see
// RegisteredUserController). Social buttons are left out on purpose: a social
// sign-in only links to an account that already exists.
const { siteKey: botCheckKey } = useBotCheck('sign_up');
const botCheck = ref<InstanceType<typeof TurnstileWidget> | null>(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    'cf-turnstile-response': '',
});

// A Turnstile token counts once, so every submit needs a new one.
const submit = () => {
    form.post(storeRegistration.url(), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
            botCheck.value?.reset();
        },
    });
};
</script>

<template>
    <AuthBase :title="t('auth.register.title')" :description="t('auth.register.description')">
        <Head :title="t('auth.register.head')" />

        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Registration Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name Field -->
                <div class="space-y-2">
                    <Label for="name" class="text-base font-medium">{{ t('auth.register.name_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <User class="h-5 w-5" />
                        </div>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            v-model="form.name"
                            :placeholder="t('auth.register.name_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">{{ t('auth.register.email_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input
                            id="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            v-model="form.email"
                            :placeholder="t('auth.register.email_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">{{ t('auth.register.password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            v-model="form.password"
                            :placeholder="t('auth.register.password_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">{{ t('auth.register.confirm_password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            :placeholder="t('auth.register.confirm_password_placeholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <div v-if="botCheckKey" class="space-y-2">
                    <TurnstileWidget ref="botCheck" v-model="form['cf-turnstile-response']" :site-key="botCheckKey" />
                    <InputError :message="form.errors['cf-turnstile-response']" />
                </div>

                <!-- Register Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">{{ t('auth.register.create_account') }}</span>
                    <span v-else>{{ t('auth.register.creating_account') }}</span>
                </Button>
            </form>

            <!-- Sign In Link -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    {{ t('auth.register.have_account') }}
                    <TextLink :href="login.url()" :tabindex="6" class="text-primary hover:text-primary/80 ms-1 font-semibold">
                        {{ t('auth.register.log_in') }}
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
