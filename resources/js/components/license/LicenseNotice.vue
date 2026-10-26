<script setup lang="ts">
// Says that something on this page needs a license key on this install (a
// free-use limit), with a link to buy one. The body names what is locked.
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { useLicense } from '@/composables/useLicense';
import { Lock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{ body: string }>();

const { t } = useI18n();
const { buyUrl } = useLicense();
</script>

<template>
    <Alert>
        <Lock />
        <AlertTitle class="flex items-center gap-2">
            {{ t('license.locked_title') }}
            <Badge variant="secondary">{{ t('license.badge') }}</Badge>
        </AlertTitle>
        <AlertDescription>
            <p>{{ body }}</p>
            <a v-if="buyUrl" :href="buyUrl" target="_blank" rel="noopener" class="font-medium underline">{{ t('license.get_key') }}</a>
        </AlertDescription>
    </Alert>
</template>
