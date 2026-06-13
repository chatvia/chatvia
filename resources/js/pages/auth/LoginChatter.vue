<script setup lang="ts">
import SocialLoginButtons from '@/components/auth/SocialLoginButtons.vue';
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useColorTheme } from '@/composables/useColorTheme';
import AuthBase from '@/layouts/AuthLayout.vue';
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

const passwordForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const magicLinkForm = useForm({
    email: '',
});

const { applyColorTheme } = useColorTheme();

const submitPassword = () => {
    passwordForm.post(route('chat.login'), {
        onFinish: () => passwordForm.reset('password'),
    });
};

const submitMagicLink = () => {
    magicLinkForm.post(route('chat.magic-link.send'), {
        preserveScroll: true,
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

                        <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="2" :disabled="magicLinkForm.processing">
                            <LoaderCircle v-if="magicLinkForm.processing" class="me-2 h-5 w-5 animate-spin" />
                            <span v-if="!magicLinkForm.processing">{{ t('auth.login_member.send_link') }}</span>
                            <span v-else>{{ t('auth.login_member.sending_link') }}</span>
                        </Button>
                    </form>
                </TabsContent>
            </Tabs>

            <!-- Divider -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t"></span>
                </div>
                <div class="relative flex justify-center text-sm uppercase">
                    <span class="bg-background text-muted-foreground px-4 font-medium">{{ t('auth.login_member.or') }}</span>
                </div>
            </div>

            <!-- Social Login Buttons (reusable component shared with the main Login page) -->
            <SocialLoginButtons />
        </div>
    </AuthBase>
</template>
