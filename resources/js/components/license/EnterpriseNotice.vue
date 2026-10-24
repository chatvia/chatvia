<script setup lang="ts">
// Says that the settings below need an Enterprise license on this install,
// and that saved values are kept until one is added.
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Lock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Alert>
        <Lock />
        <AlertTitle class="flex items-center gap-2">
            {{ t('workspace.enterprise.locked_title') }}
            <Badge variant="secondary">{{ t('workspace.enterprise.badge') }}</Badge>
        </AlertTitle>
        <AlertDescription>{{ t('workspace.enterprise.locked_body') }}</AlertDescription>
    </Alert>
</template>
