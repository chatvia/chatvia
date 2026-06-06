<script setup lang="ts">
import LoginLink from '@/../../vendor/spatie/laravel-login-link/resources/js/login-link.vue';
import SocialLoginButtons from '@/components/auth/SocialLoginButtons.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Fingerprint, LoaderCircle, Lock, Mail, Sparkles } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const mode = ref<'password' | 'magic-link'>('password');

const passwordForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const magicLinkForm = useForm({
    email: '',
});

const submitPassword = () => {
    passwordForm.post(route('login'), {
        onFinish: () => passwordForm.reset('password'),
    });
};

const submitMagicLink = () => {
    magicLinkForm.post(route('magic-link.send'), {
        preserveScroll: true,
    });
};

const passkeyError = ref<string | null>(null);
const passkeyPending = ref(false);

let passkeysModule: Promise<typeof import('@laravel/passkeys')> | null = null;
const loadPasskeys = () => (passkeysModule ??= import('@laravel/passkeys'));

const redirectAfterSignIn = (redirect?: string) => {
    // Hard-navigate so the new authenticated session is picked up on load.
    window.location.href = redirect ?? route('dashboard');
};

const tryAutofill = async () => {
    const { Passkeys, UserCancelledError } = await loadPasskeys();
    if (!(await Passkeys.isAutofillSupported())) return;
    try {
        const result = await Passkeys.autofill();
        if (result) redirectAfterSignIn(result.redirect);
    } catch (e: unknown) {
        if (e instanceof UserCancelledError) return;
        passkeyError.value = e instanceof Error ? e.message : 'Could not sign in with a passkey.';
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
            passkeyError.value = e instanceof Error ? e.message : 'Could not sign in with a passkey.';
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
    <AuthBase title="Welcome back" description="Sign in to your account to continue">
        <Head title="Log in" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <LoginLink v-if="$page.props.environment === 'local'" label="Login" class="pb-3 text-red-500" :redirect-url="route('dashboard')" />

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
                        Password
                    </TabsTrigger>
                    <TabsTrigger value="magic-link" class="flex items-center gap-2 text-sm font-medium">
                        <Sparkles class="h-4 w-4" />
                        Magic link
                    </TabsTrigger>
                    <button
                        type="button"
                        :disabled="passkeyPending"
                        @click="signInWithPasskey"
                        class="text-foreground dark:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-ring hover:bg-background/50 inline-flex h-[calc(100%-1px)] flex-1 cursor-pointer items-center justify-center gap-2 rounded-md border border-transparent px-2 py-1 text-sm font-medium whitespace-nowrap transition-[color,box-shadow] focus-visible:ring-[3px] focus-visible:outline-1 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4"
                    >
                        <LoaderCircle v-if="passkeyPending" class="animate-spin" />
                        <Fingerprint v-else />
                        Passkey
                    </button>
                </TabsList>

                <p v-if="passkeyError" class="text-destructive pt-2 text-center text-sm">{{ passkeyError }}</p>

                <TabsContent value="password" class="mt-6">
                    <form @submit.prevent="submitPassword" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="email" class="text-base font-medium">Email address</Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
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
                                    placeholder="email@example.com"
                                    class="h-12 pl-10 text-base"
                                />
                            </div>
                            <InputError :message="passwordForm.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="password" class="text-base font-medium">Password</Label>
                                <TextLink
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="text-primary hover:text-primary/80 text-sm font-medium"
                                    :tabindex="5"
                                >
                                    Forgot password?
                                </TextLink>
                            </div>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <Lock class="h-5 w-5" />
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    :tabindex="2"
                                    autocomplete="current-password"
                                    v-model="passwordForm.password"
                                    placeholder="Enter your password"
                                    class="h-12 pl-10 text-base"
                                />
                            </div>
                            <InputError :message="passwordForm.errors.password" />
                        </div>

                        <div class="flex items-center space-x-2">
                            <Checkbox id="remember" v-model="passwordForm.remember" :tabindex="3" />
                            <Label for="remember" class="cursor-pointer text-sm font-medium"> Remember me for 30 days </Label>
                        </div>

                        <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="4" :disabled="passwordForm.processing">
                            <LoaderCircle v-if="passwordForm.processing" class="mr-2 h-5 w-5 animate-spin" />
                            <span v-if="!passwordForm.processing">Sign in to your account</span>
                            <span v-else>Signing in...</span>
                        </Button>
                    </form>
                </TabsContent>

                <TabsContent value="magic-link" class="mt-6">
                    <form @submit.prevent="submitMagicLink" class="space-y-6">
                        <p class="text-muted-foreground text-sm">
                            Enter your email and we'll send you a single-use link to sign in — no password required.
                        </p>

                        <div class="space-y-2">
                            <Label for="magic-email" class="text-base font-medium">Email address</Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <Mail class="h-5 w-5" />
                                </div>
                                <Input
                                    id="magic-email"
                                    type="email"
                                    required
                                    :tabindex="1"
                                    autocomplete="email webauthn"
                                    v-model="magicLinkForm.email"
                                    placeholder="email@example.com"
                                    class="h-12 pl-10 text-base"
                                />
                            </div>
                            <InputError :message="magicLinkForm.errors.email" />
                        </div>

                        <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="2" :disabled="magicLinkForm.processing">
                            <LoaderCircle v-if="magicLinkForm.processing" class="mr-2 h-5 w-5 animate-spin" />
                            <span v-if="!magicLinkForm.processing">Send me a sign-in link</span>
                            <span v-else>Sending link...</span>
                        </Button>
                    </form>
                </TabsContent>
            </Tabs>

            <!-- Divider -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t"></span>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="bg-background text-muted-foreground px-4 font-medium">Or continue with</span>
                </div>
            </div>

            <!-- Social Login Buttons -->
            <SocialLoginButtons show-microsoft show-github />

            <!-- Sign Up Link -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    Don't have an account?
                    <a href="/#pricing" :tabindex="8" class="text-primary hover:text-primary/80 ml-1 font-semibold"> Start free trial </a>
                </p>
            </div>
        </div>
    </AuthBase>
</template>
