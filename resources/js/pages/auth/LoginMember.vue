<script setup lang="ts">
import SocialLoginButtons from '@/components/auth/SocialLoginButtons.vue';
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useColorTheme } from '@/composables/useColorTheme';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, Mail } from 'lucide-vue-next';
import { onMounted } from 'vue';

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    agentName?: string;
    appearance?: {
        color_theme?: string;
        welcome_message?: string;
        chat_window_title?: string;
        chat_footer_text?: string;
        avatar?: string;
    };
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const { applyColorTheme } = useColorTheme();

const submit = () => {
    form.post(route('chat.login'), {
        onFinish: () => form.reset('password'),
    });
};

onMounted(() => {
    applyColorTheme(props.appearance);
});
</script>

<template>
    <AuthBase :title="agentName ?? ''" description="Sign in to access this chat workspace">
        <Head title="Member Login" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <div v-if="status" class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950/50">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <!-- Login Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
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
                            autocomplete="email"
                            v-model="form.email"
                            placeholder="member@company.com"
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
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
                            v-model="form.password"
                            placeholder="Enter your password"
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Remember Me -->
                <div class="flex items-center space-x-2">
                    <Checkbox id="remember" v-model="form.remember" :tabindex="3" />
                    <Label for="remember" class="cursor-pointer text-sm font-medium"> Keep me signed in </Label>
                </div>

                <!-- Login Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">Start Chatting</span>
                    <span v-else>Signing in...</span>
                </Button>
            </form>

            <!-- Divider -->
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t"></span>
                </div>
                <div class="relative flex justify-center text-sm uppercase">
                    <span class="bg-background text-muted-foreground px-4 font-medium">Or</span>
                </div>
            </div>

            <!-- Social Login Buttons (reusable component shared with the main Login page) -->
            <SocialLoginButtons />
        </div>
    </AuthBase>
</template>
