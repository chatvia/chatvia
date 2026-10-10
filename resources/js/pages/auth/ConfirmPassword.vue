<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store as confirmPassword } from '@/routes/password/confirm';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, ShieldCheck } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(confirmPassword.url(), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <AuthLayout :title="t('auth.confirm_password.title')" :description="t('auth.confirm_password.description')">
        <Head :title="t('auth.confirm_password.head')" />

        <form @submit.prevent="submit" class="mx-auto w-full max-w-lg space-y-6">
            <div class="space-y-2">
                <Label for="password" class="text-base font-medium">{{ t('auth.confirm_password.password_label') }}</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <Lock class="h-5 w-5" />
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        autofocus
                        autocomplete="current-password"
                        v-model="form.password"
                        :placeholder="t('auth.confirm_password.password_placeholder')"
                        class="h-12 ps-10 text-base"
                    />
                </div>
                <InputError :message="form.errors.password" />
            </div>

            <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="me-2 h-5 w-5 animate-spin" />
                <ShieldCheck v-else class="me-2 h-5 w-5" />
                <span v-if="!form.processing">{{ t('auth.confirm_password.confirm_password') }}</span>
                <span v-else>{{ t('auth.confirm_password.confirming') }}</span>
            </Button>
        </form>
    </AuthLayout>
</template>
