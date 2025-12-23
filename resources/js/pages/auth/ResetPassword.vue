<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, LockKeyhole, Mail } from 'lucide-vue-next';

interface Props {
    token: string;
    email: string;
}

const props = defineProps<Props>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout title="Reset password" description="Please enter your new password below">
        <Head title="Reset password" />

        <div class="absolute top-4 right-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Reset Password Form -->
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
                            class="h-12 pl-10 text-base"
                            readonly
                            placeholder="email@example.com"
                        />
                    </div>
                    <InputError :message="form.errors.email" />
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">Password</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            v-model="form.password"
                            class="h-12 pl-10 text-base"
                            autofocus
                            placeholder="Password"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">Confirm password</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            class="h-12 pl-10 text-base"
                            placeholder="Confirm password"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Reset Password Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">Reset password</span>
                    <span v-else>Resetting password...</span>
                </Button>
            </form>
        </div>
    </AuthLayout>
</template>
