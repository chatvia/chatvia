<script setup lang="ts">
// Workspace sign-in: password or magic link (tabs), passkeys (button and
// browser autofill), and social sign-in. Passkey autofill is armed on mount
// and cancelled on leave so the browser prompt does not outlive the page.
import SocialLoginButtons from '@/auth/components/SocialLoginButtons.vue';
import TurnstileWidget from '@/auth/components/TurnstileWidget.vue';
import InputError from '@/shared/components/InputError.vue';
import TextLink from '@/auth/components/TextLink.vue';
import ThemeSwitcher from '@/shared/components/ThemeSwitcher.vue';
import { Button } from '@/shared/ui/button';
import { Checkbox } from '@/shared/ui/checkbox';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/shared/ui/tabs';
import { useBotCheck } from '@/auth/composables/useBotCheck';
import { useSocialProviders } from '@/shared/composables/useSocialProviders';
import AuthBase from '@/shared/layouts/AuthLayout.vue';
import { dashboard, login, register } from '@/routes';
import { send as sendMagicLink } from '@/routes/magic-link';
import { request as requestPasswordReset } from '@/routes/password';
import { Head, useForm } from '@inertiajs/vue3';
import { Fingerprint, LoaderCircle, Lock, Mail, Sparkles } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    status?: string;
    canResetPassword: boolean;
    // Self-serve sign-up is open ("Open sign-up" in the admin panel).
    canRegister?: boolean;
}>();

const mode = ref<'password' | 'magic-link'>('password');

const { siteKey: botCheckKey } = useBotCheck('sign_in');
const { hasAny: hasAnySocialProvider } = useSocialProviders();
const passwordBotCheck = ref<InstanceType<typeof TurnstileWidget> | null>(null);
const magicLinkBotCheck = ref<InstanceType<typeof TurnstileWidget> | null>(null);

const passwordForm = useForm({
    email: '',
    password: '',
    remember: false,
    'cf-turnstile-response': '',
});

const magicLinkForm = useForm({
    email: '',
    'cf-turnstile-response': '',
});

// A Turnstile token counts once, so every submit needs a new one.
const submitPassword = () => {
    passwordForm.post(login.url(), {
        onFinish: () => {
            passwordForm.reset('password');
            passwordBotCheck.value?.reset();
        },
    });
};

const submitMagicLink = () => {
    magicLinkForm.post(sendMagicLink.url(), {
        preserveScroll: true,
        onFinish: () => magicLinkBotCheck.value?.reset(),
    });
};

const passkeyError = ref<string | null>(null);
const passkeyPending = ref(false);

let passkeysModule: Promise<typeof import('@laravel/passkeys')> | null = null;
const loadPasskeys = () => (passkeysModule ??= import('@laravel/passkeys'));

const redirectAfterSignIn = (redirect?: string) => {
    // Hard-navigate so the new authenticated session is picked up on load.
    window.location.href = redirect ?? dashboard.url();
};

const tryAutofill = async () => {
    const { Passkeys, UserCancelledError } = await loadPasskeys();
    if (!(await Passkeys.isAutofillSupported())) return;
    try {
        const result = await Passkeys.autofill();
        if (result) redirectAfterSignIn(result.redirect);
    } catch (e: unknown) {
        if (e instanceof UserCancelledError) return;
        passkeyError.value = e instanceof Error ? e.message : t('auth.login.passkey_error_fallback');
    }
};

const signInWithPasskey = async () => {
    passkeyError.value = null;
    passkeyPending.value = true;
    const { Passkeys, UserCancelledError } = await loadPasskeys();
    try {
        Passkeys.cancel();
        const result = await Passkeys.verify();
        redirectAfterSignIn(result.redirect);
    } catch (e: unknown) {
        passkeyPending.value = false;
        if (!(e instanceof UserCancelledError)) {
            passkeyError.value = e instanceof Error ? e.message : t('auth.login.passkey_error_fallback');
        }
        // Re-arm autofill so the field-anchored dropdown still works after a cancelled modal verify.
        tryAutofill();
    }
};

const cancelPasskeys = () => {
    passkeysModule?.then(({ Passkeys }) => Passkeys.cancel());
};

onMounted(tryAutofill);
onBeforeUnmount(cancelPasskeys);
</script>

<template>
    <AuthBase :title="t('auth.login.title')" :description="t('auth.login.description')">
        <Head :title="t('auth.login.head')" />

        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Header Section -->

            <!-- Status ChatMessage -->
            <div v-if="status" class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <Tabs v-model="mode" class="w-full">
                <TabsList class="grid h-11 w-full grid-cols-3">
                    <TabsTrigger value="password" class="flex items-center gap-2 text-sm font-medium">
                        <Lock class="h-4 w-4" />
                        {{ t('auth.login.password_tab') }}
                    </TabsTrigger>
                    <TabsTrigger value="magic-link" class="flex items-center gap-2 text-sm font-medium">
                        <Sparkles class="h-4 w-4" />
                        {{ t('auth.login.magic_link_tab') }}
                    </TabsTrigger>
                    <button
                        type="button"
                        :disabled="passkeyPending"
                        @click="signInWithPasskey"
                        class="text-foreground dark:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring hover:bg-background/50 inline-flex h-[calc(100%-1px)] flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
                    >
                        <LoaderCircle v-if="passkeyPending" class="animate-spin" />
                        <Fingerprint v-else />
                        {{ t('auth.login.passkey_tab') }}
                    </button>
                </TabsList>

                <p v-if="passkeyError" class="text-destructive pt-2 text-center text-sm">{{ passkeyError }}</p>

                <TabsContent value="password" class="mt-6">
                    <form @submit.prevent="submitPassword" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="email" class="text-base font-medium">{{ t('auth.login.email_label') }}</Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                    <Mail class="h-5 w-5" />
                                </div>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autofocus
                                    :tabindex="1"
                                    autocomplete="email webauthn"
                                    v-model="passwordForm.email"
                                    :placeholder="t('auth.login.email_placeholder')"
                                    class="h-12 ps-10 text-base"
                                />
                            </div>
                            <InputError :message="passwordForm.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="password" class="text-base font-medium">{{ t('auth.login.password_label') }}</Label>
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="requestPasswordReset.url()"
                                    class="text-primary hover:text-primary/80 text-sm font-medium"
                                    :tabindex="5"
                                >
                                    {{ t('auth.login.forgot_password') }}
                                </TextLink>
                            </div>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                    <Lock class="h-5 w-5" />
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    v-model="passwordForm.password"
                                    :placeholder="t('auth.login.password_placeholder')"
                                    class="h-12 ps-10 text-base"
                                />
                            </div>
                            <InputError :message="passwordForm.errors.password" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="remember" v-model="passwordForm.remember" :tabindex="3" />
                            <Label for="remember" class="cursor-pointer text-sm font-medium"> {{ t('auth.login.remember_me') }} </Label>
                        </div>

                        <div v-if="botCheckKey" class="space-y-2">
                            <TurnstileWidget ref="passwordBotCheck" v-model="passwordForm['cf-turnstile-response']" :site-key="botCheckKey" />
                            <InputError :message="passwordForm.errors['cf-turnstile-response']" />
                        </div>

                        <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="4" :disabled="passwordForm.processing">
                            <LoaderCircle v-if="passwordForm.processing" class="me-2 h-5 w-5 animate-spin" />
                            <span v-if="!passwordForm.processing">{{ t('auth.login.sign_in') }}</span>
                            <span v-else>{{ t('auth.login.signing_in') }}</span>
                        </Button>
                    </form>
                </TabsContent>

                <TabsContent value="magic-link" class="mt-6">
                    <form @submit.prevent="submitMagicLink" class="space-y-6">
                        <p class="text-muted-foreground text-sm">
                            {{ t('auth.login.magic_link_description') }}
                        </p>

                        <div class="space-y-2">
                            <Label for="magic-email" class="text-base font-medium">{{ t('auth.login.email_label') }}</Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                    <Mail class="h-5 w-5" />
                                </div>
                                <Input
                                    id="magic-email"
                                    type="email"
                                    required
                                    :tabindex="1"
                                    autocomplete="email webauthn"
                                    v-model="magicLinkForm.email"
                                    :placeholder="t('auth.login.email_placeholder')"
                                    class="h-12 ps-10 text-base"
                                />
                            </div>
                            <InputError :message="magicLinkForm.errors.email" />
                        </div>

                        <div v-if="botCheckKey" class="space-y-2">
                            <TurnstileWidget ref="magicLinkBotCheck" v-model="magicLinkForm['cf-turnstile-response']" :site-key="botCheckKey" />
                            <InputError :message="magicLinkForm.errors['cf-turnstile-response']" />
                        </div>

                        <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="2" :disabled="magicLinkForm.processing">
                            <LoaderCircle v-if="magicLinkForm.processing" class="me-2 h-5 w-5 animate-spin" />
                            <span v-if="!magicLinkForm.processing">{{ t('auth.login.send_link') }}</span>
                            <span v-else>{{ t('auth.login.sending_link') }}</span>
                        </Button>
                    </form>
                </TabsContent>
            </Tabs>

            <!-- Social sign-in: only when at least one provider is set up -->
            <template v-if="hasAnySocialProvider(['google', 'slack', 'microsoft', 'github'])">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <span class="w-full border-t"></span>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="bg-background text-muted-foreground px-4 font-medium">{{ t('auth.login.or_continue_with') }}</span>
                    </div>
                </div>

                <SocialLoginButtons show-microsoft show-github />
            </template>

            <!-- Sign Up Link: only when self-serve sign-up is open -->
            <div v-if="canRegister" class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    {{ t('auth.login.no_account') }}
                    <TextLink :href="register.url()" :tabindex="8" class="text-primary hover:text-primary/80 ms-1 font-semibold">
                        {{ t('auth.login.create_account') }}
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
