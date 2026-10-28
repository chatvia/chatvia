<script setup lang="ts">
import InputError from '@/shared/components/InputError.vue';
import AppLayout from '@/dashboard/layouts/AppLayout.vue';
import AccountLayout from '@/dashboard/account/layouts/Layout.vue';
import { update as updatePasswordRoute } from '@/routes/account/password';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Separator } from '@/shared/ui/separator';
import { type BreadcrumbItem } from '@/shared/types';
import { CheckCircle, KeyRound, Lock, Save, Shield } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: t('account.password.title'),
        href: '/my/account/password',
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
    form.put(updatePasswordRoute.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: (errors) => {
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
        <Head :title="t('account.password.title')" />

        <AccountLayout>
            <div class="space-y-8">
                <!-- Password Security Header -->
                <div class="flex flex-col space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="bg-primary/10 rounded-full p-2">
                            <Shield class="text-primary size-6" />
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight">{{ t('account.password.heading') }}</h1>
                    </div>
                    <p class="text-muted-foreground">{{ t('account.password.subtitle') }}</p>
                </div>

                <Separator />

                <!-- Password Update Form -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('account.password.update_title') }}</CardTitle>
                        <CardDescription>{{ t('account.password.update_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="updatePassword" class="space-y-6">
                            <div class="grid gap-2">
                                <Label for="current_password">{{ t('account.password.current') }}</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <KeyRound class="size-4" />
                                    </div>
                                    <Input
                                        id="current_password"
                                        ref="currentPasswordInput"
                                        v-model="form.current_password"
                                        type="password"
                                        class="focus-visible:ring-primary/50 ps-9 transition-all duration-200"
                                        autocomplete="current-password"
                                        :placeholder="t('account.password.current_placeholder')"
                                    />
                                </div>
                                <InputError :message="form.errors.current_password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password">{{ t('account.password.new') }}</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <Lock class="size-4" />
                                    </div>
                                    <Input
                                        id="password"
                                        ref="passwordInput"
                                        v-model="form.password"
                                        type="password"
                                        class="focus-visible:ring-primary/50 ps-9 transition-all duration-200"
                                        autocomplete="new-password"
                                        :placeholder="t('account.password.new_placeholder')"
                                    />
                                </div>
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="password_confirmation">{{ t('account.password.confirm') }}</Label>
                                <div class="relative">
                                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                                        <Lock class="size-4" />
                                    </div>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="focus-visible:ring-primary/50 ps-9 transition-all duration-200"
                                        autocomplete="new-password"
                                        :placeholder="t('account.password.confirm_placeholder')"
                                    />
                                </div>
                                <InputError :message="form.errors.password_confirmation" />
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button type="submit" :disabled="form.processing" class="transition-all duration-200">
                                    <Save class="me-2 size-4" />
                                    {{ t('account.password.submit') }}
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
                                        <CheckCircle class="me-1 size-4" />
                                        <span>{{ t('account.password.updated') }}</span>
                                    </div>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AccountLayout>
    </AppLayout>
</template>
