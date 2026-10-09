<script setup lang="ts">
/**
 * The small outline "Copy" button on the Deploy page. Shows a check and
 * "Copied!" while `copied` is true. Clicks and extra classes fall through to
 * the Button.
 */
import { Button } from '@/components/ui/button';
import { Check, Copy } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    copied: boolean;
}>();

const { t } = useI18n();
</script>

<template>
    <Button type="button" variant="outline" size="sm" class="gap-2">
        <component :is="copied ? Check : Copy" class="size-4" />
        {{ copied ? t('agents.deploy.copied') : t('agents.deploy.copy') }}
    </Button>
</template>
