<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, LockKeyhole, Mail } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    email: string;
}

defineProps<Props>();

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.setup.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.set_password.title')" :description="t('auth.set_password.description')">
        <Head :title="t('auth.set_password.head')" />

        <div class="absolute top-4 end-4">
            <ThemeSwitcher />
        </div>

        <!-- Clean, spacious container -->
        <div class="mx-auto w-full max-w-lg space-y-8">
            <!-- Set Password Form -->
            <form @submit.prevent="submit" class="space-y-6">
                <!-- Email Field (display only) -->
                <div class="space-y-2">
                    <Label for="email" class="text-base font-medium">{{ t('auth.set_password.email_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Mail class="h-5 w-5" />
                        </div>
                        <Input id="email" type="email" name="email" :model-value="email" class="h-12 ps-10 text-base" readonly />
                    </div>
                </div>

                <!-- Password Field -->
                <div class="space-y-2">
                    <Label for="password" class="text-base font-medium">{{ t('auth.set_password.password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <Lock class="h-5 w-5" />
                        </div>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            v-model="form.password"
                            class="h-12 ps-10 text-base"
                            autofocus
                            :placeholder="t('auth.set_password.password_placeholder')"
                        />
                    </div>
                    <InputError :message="form.errors.password" />
                </div>

                <!-- Confirm Password Field -->
                <div class="space-y-2">
                    <Label for="password_confirmation" class="text-base font-medium">{{ t('auth.set_password.confirm_password_label') }}</Label>
                    <div class="relative">
                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                            <LockKeyhole class="h-5 w-5" />
                        </div>
                        <Input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            v-model="form.password_confirmation"
                            class="h-12 ps-10 text-base"
                            :placeholder="t('auth.set_password.confirm_password_placeholder')"
                        />
                    </div>
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <!-- Set Password Button -->
                <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                    <span v-if="!form.processing">{{ t('auth.set_password.set_password') }}</span>
                    <span v-else>{{ t('auth.set_password.setting_password') }}</span>
                </Button>
            </form>
        </div>
    </AuthLayout>
</template>
