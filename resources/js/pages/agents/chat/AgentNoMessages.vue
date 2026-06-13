<script setup lang="ts">
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import AuthBase from '@/layouts/AuthLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AuthBase :title="t('agents.chat_errors.no_messages_title')" :description="t('agents.chat_errors.no_messages_description')">
        <div class="absolute end-4 top-4">
            <ThemeSwitcher />
        </div>
    </AuthBase>
</template>
