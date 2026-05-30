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
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, Mail } from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
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
                            placeholder="email@example.com"
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password Field -->
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
                    <Label for="remember" class="cursor-pointer text-sm font-medium"> Remember me for 30 days </Label>
                </div>

                <!-- Login Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">Sign in to your account</span>
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

            <!-- Social Login Buttons -->
            <SocialLoginButtons />

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
