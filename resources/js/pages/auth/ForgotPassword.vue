<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <AuthLayout title="Forgot password" description="Enter your email to receive a password reset link">
        <Head title="Forgot password" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="w-full max-w-lg mx-auto space-y-8">
            <!-- Status ChatMessage -->
            <div v-if="status" class="p-4 bg-green-50 dark:bg-green-950/50 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-center text-sm font-medium text-green-600 dark:text-green-400">
                    {{ status }}
                </p>
            </div>

            <!-- Forgot Password Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field -->
                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">Email address</Label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            v-model="form.email"
                            autofocus
                            placeholder="email@example.com"
                            class="h-12 text-base pl-10"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Send Reset Link Button -->
                <Button
                    type="submit"
                    class="w-full h-12 text-base font-semibold"
                    :disabled="form.processing"
                >
                    <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin mr-2" />
                    <span v-if="!form.processing">Send password reset link</span>
                    <span v-else>Sending reset link...</span>
                </Button>
            </form>

            <!-- Sign In Link -->
            <div class="text-center pt-4">
                <p class="text-base text-muted-foreground">
                    Or, return to
                    <TextLink
                        :href="route('login')"
                        class="font-semibold text-primary hover:text-primary/80 ml-1"
                    >
                        log in
                    </TextLink>
                </p>
            </div>
        </div>
    </AuthLayout>
</template>
