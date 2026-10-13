<script setup lang="ts">
// Chatter sign-in for a protected agent's hosted chat: password or magic
// link (the magic link also covers a forgotten password). Uses the agent's colour theme so the page
// matches the chat the chatter is trying to reach.
import TurnstileWidget from '@/components/auth/TurnstileWidget.vue';
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useBotCheck } from '@/composables/useBotCheck';
import { useColorTheme } from '@/composables/useColorTheme';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login as chatterLogin } from '@/routes/chat';
import { send as sendChatterMagicLink } from '@/routes/chat/magic-link';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, Mail, Sparkles } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    agentName?: string;
    appearance?: {
        color_theme?: string;
        welcome_message?: string;
        chat_window_title?: string;
        avatar?: string;
    };
}>();

const mode = ref<'password' | 'magic-link'>('password');

// The bot check of the sign-in forms (Settings → Security), when it is on.
const { siteKey: botCheckKey } = useBotCheck('sign_in');
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

const { applyColorTheme } = useColorTheme();

// A Turnstile token counts once, so every submit needs a new one.
const submitPassword = () => {
    passwordForm.post(chatterLogin.url(), {
        onFinish: () => {
            passwordForm.reset('password');
            passwordBotCheck.value?.reset();
        },
    });
};

const submitMagicLink = () => {
    magicLinkForm.post(sendChatterMagicLink.url(), {
        preserveScroll: true,
        onFinish: () => magicLinkBotCheck.value?.reset(),
    });
};

onMounted(() => {
    applyColorTheme(props.appearance);
});
</script>

<template>
    <AuthBase :title="agentName ?? ''" :description="t('auth.login_member.description')">
        <Head :title="t('auth.login_member.head')" />

        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <div v-if="status" class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <Tabs v-model="mode" class="w-full">
                <TabsList class="grid h-11 w-full grid-cols-2">
                    <TabsTrigger value="password" class="flex items-center gap-2 text-sm font-medium">
                        <Lock class="h-4 w-4" />
                        {{ t('auth.login_member.password_tab') }}
                    </TabsTrigger>
                    <TabsTrigger value="magic-link" class="flex items-center gap-2 text-sm font-medium">
                        <Sparkles class="h-4 w-4" />
                        {{ t('auth.login_member.magic_link_tab') }}
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="password" class="mt-6">
                    <form @submit.prevent="submitPassword" class="space-y-6">
                        <div class="space-y-2">
                            <Label for="email" class="text-base font-medium">{{ t('auth.login_member.email_label') }}</Label>
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
                                    autocomplete="email"
                                    v-model="passwordForm.email"
                                    :placeholder="t('auth.login_member.email_placeholder')"
                                    class="h-12 ps-10 text-base"
                                />
                            </div>
                            <InputError :message="passwordForm.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password" class="text-base font-medium">{{ t('auth.login_member.password_label') }}</Label>
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
                                    :placeholder="t('auth.login_member.password_placeholder')"
                                    class="h-12 ps-10 text-base"
                                />
                            </div>
                            <InputError :message="passwordForm.errors.password" />
                        </div>

                        <div class="flex items-center gap-2">
                            <Checkbox id="remember" v-model="passwordForm.remember" :tabindex="3" />
                            <Label for="remember" class="cursor-pointer text-sm font-medium"> {{ t('auth.login_member.keep_signed_in') }} </Label>
                        </div>

                        <div v-if="botCheckKey" class="space-y-2">
                            <TurnstileWidget ref="passwordBotCheck" v-model="passwordForm['cf-turnstile-response']" :site-key="botCheckKey" />
                            <InputError :message="passwordForm.errors['cf-turnstile-response']" />
                        </div>

                        <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="4" :disabled="passwordForm.processing">
                            <LoaderCircle v-if="passwordForm.processing" class="me-2 h-5 w-5 animate-spin" />
                            <span v-if="!passwordForm.processing">{{ t('auth.login_member.start_chatting') }}</span>
                            <span v-else>{{ t('auth.login_member.signing_in') }}</span>
                        </Button>
                    </form>
                </TabsContent>

                <TabsContent value="magic-link" class="mt-6">
                    <form @submit.prevent="submitMagicLink" class="space-y-6">
                        <p class="text-muted-foreground text-sm">
                            {{ t('auth.login_member.magic_link_description') }}
                        </p>

                        <div class="space-y-2">
                            <Label for="magic-email" class="text-base font-medium">{{ t('auth.login_member.email_label') }}</Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                    <Mail class="h-5 w-5" />
                                </div>
                                <Input
                                    id="magic-email"
                                    type="email"
                                    required
                                    :tabindex="1"
                                    autocomplete="email"
                                    v-model="magicLinkForm.email"
                                    :placeholder="t('auth.login_member.email_placeholder')"
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
                            <span v-if="!magicLinkForm.processing">{{ t('auth.login_member.send_link') }}</span>
                            <span v-else>{{ t('auth.login_member.sending_link') }}</span>
                        </Button>
                    </form>
                </TabsContent>
            </Tabs>
        </div>
    </AuthBase>
</template>
