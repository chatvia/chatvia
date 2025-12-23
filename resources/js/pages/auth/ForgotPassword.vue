<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Mail } from 'lucide-vue-next';

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
                    <Label for="email" class="text-base font-medium">Email address</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
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
                            class="h-12 pl-10 text-base"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Send Reset Link Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">Send password reset link</span>
                    <span v-else>Sending reset link...</span>
                </Button>
            </form>

            <!-- Sign In Link -->
            <div class="pt-4 text-center">
                <p class="text-muted-foreground text-base">
                    Or, return to
                    <TextLink :href="route('login')" class="text-primary hover:text-primary/80 ml-1 font-semibold"> log in </TextLink>
                </p>
            </div>
        </div>
    </AuthLayout>
</template>
