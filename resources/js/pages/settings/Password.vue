<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { type BreadcrumbItem } from '@/types';
import { AlertTriangle, CheckCircle, KeyRound, Lock, Save, Shield } from 'lucide-vue-next';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: '/settings/password',
    },
];

const passwordInput = ref<HTMLInputElement | null>(null);
const currentPasswordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('settings.password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors: any) => {
            if (errors.password) {
                form.reset('password', 'password_confirmation');
                if (passwordInput.value instanceof HTMLInputElement) {
                    passwordInput.value.focus();
                }
            }

            if (errors.current_password) {
                form.reset('current_password');
                if (currentPasswordInput.value instanceof HTMLInputElement) {
                    currentPasswordInput.value.focus();
                }
            }
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Password settings" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Password Security Header -->
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center space-x-3">
                        <div class="bg-primary/10 rounded-full p-2">
                            <Shield class="text-primary size-6" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">Password Security</h1>
                    </div>
                    <p class="text-muted-foreground">Manage your account's password.</p>
                </div>

                <Separator />

                <!-- Password Update Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>Update Password</CardTitle>
                        <CardDescription> Ensure your account is using a strong, secure password to protect your account. </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updatePassword" class="space-y-6">
                            <div class="grid gap-2">
                                <Label for="current_password">Current password</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <KeyRound class="size-4" />
                                    </div>
                                    <Input
                                        id="current_password"
                                        ref="currentPasswordInput"
                                        v-model="form.current_password"
                                        type="password"
                                        class="focus-visible:ring-primary/50 pl-9 transition-all duration-200"
                                        autocomplete="current-password"
                                        placeholder="Enter your current password"
                                    />
                                </div>
                                <InputError :message="form.errors.current_password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">New password</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <Lock class="size-4" />
                                    </div>
                                    <Input
                                        id="password"
                                        ref="passwordInput"
                                        v-model="form.password"
                                        type="password"
                                        class="focus-visible:ring-primary/50 pl-9 transition-all duration-200"
                                        autocomplete="new-password"
                                        placeholder="Enter your new password"
                                    />
                                </div>
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">Confirm password</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                        <Lock class="size-4" />
                                    </div>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="focus-visible:ring-primary/50 pl-9 transition-all duration-200"
                                        autocomplete="new-password"
                                        placeholder="Confirm your new password"
                                    />
                                </div>
                                <InputError :message="form.errors.password_confirmation" />
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button type="submit" :disabled="form.processing" class="transition-all duration-200">
                                    <Save class="mr-2 size-4" />
                                    Update password
                                </Button>

                                <Transition
                                    enter-active-class="transition ease-in-out duration-300"
                                    enter-from-class="opacity-0 transform translate-x-4"
                                    enter-to-class="opacity-100 transform translate-x-0"
                                    leave-active-class="transition ease-in-out duration-300"
                                    leave-from-class="opacity-100 transform translate-x-0"
                                    leave-to-class="opacity-0 transform translate-x-4"
                                >
                                    <div v-show="form.recentlySuccessful" class="flex items-center text-sm text-green-600">
                                        <CheckCircle class="mr-1 size-4" />
                                        <span>Password updated successfully</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
