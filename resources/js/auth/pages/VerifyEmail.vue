<script setup lang="ts">
import TextLink from '@/auth/components/TextLink.vue';
import { Button } from '@/shared/ui/button';
import AuthLayout from '@/shared/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send as sendVerification } from '@/routes/verification';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(sendVerification.url());
};
</script>

<template>
    <AuthLayout :title="t('auth.verify_email.title')" :description="t('auth.verify_email.description')">
        <Head :title="t('auth.verify_email.head')" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ t('auth.verify_email.link_sent') }}
        </div>

        <form @submit.prevent="submit" class="space-y-6 text-center">
            <Button :disabled="form.processing" variant="secondary">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ t('auth.verify_email.resend') }}
            </Button>

            <TextLink :href="logout.url()" method="post" as="button" class="mx-auto block text-sm">
                {{ t('auth.verify_email.log_out') }}
            </TextLink>
        </form>
    </AuthLayout>
</template>
