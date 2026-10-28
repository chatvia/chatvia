<script setup lang="ts">
// Says that something on this page is unavailable on this install (it needs
// a license key or an Enterprise license). Staff cannot add a key from the
// dashboard, so it sends them to their administrator. `body` replaces the
// default line when the lock needs more words.
import { Alert, AlertDescription, AlertTitle } from '@/shared/ui/alert';
import { Lock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{ body?: string }>();

const { t } = useI18n();
</script>

<template>
    <Alert>
        <Lock />
        <AlertTitle>{{ t('license.unavailable_title') }}</AlertTitle>
        <AlertDescription>{{ body ?? t('license.unavailable_body') }}</AlertDescription>
    </Alert>
</template>
