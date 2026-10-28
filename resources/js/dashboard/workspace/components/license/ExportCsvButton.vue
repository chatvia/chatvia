<script setup lang="ts">
// Downloads an audit log as CSV. Exporting is an Enterprise feature: without
// an Enterprise key the button is disabled and says why.
import { Button } from '@/shared/ui/button';
import { useEnterprise } from '@/dashboard/workspace/composables/useEnterprise';
import { Download, Lock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    // The export URL, with the page's current filters.
    href: string;
}>();

const { t } = useI18n();
const { enterprise } = useEnterprise();
</script>

<template>
    <Button v-if="enterprise" as="a" :href="href" variant="outline" size="sm">
        <Download class="me-2 size-4" />
        {{ t('workspace.enterprise.export_csv') }}
    </Button>
    <Button v-else variant="outline" size="sm" disabled :title="t('license.unavailable')">
        <Lock class="me-2 size-4" />
        {{ t('workspace.enterprise.export_csv') }}
    </Button>
</template>
