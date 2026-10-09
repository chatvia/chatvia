<script setup lang="ts">
/**
 * The Hosted Chat's public link on the Deploy page, with buttons to copy it
 * and to open it in a new tab.
 */
import DeployCopyButton from '@/components/agents/deploy/DeployCopyButton.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ExternalLink } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    url: string;
    copied: boolean;
}>();

const emit = defineEmits<{
    copy: [];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="space-y-2">
        <Label class="text-sm font-medium">{{ t('agents.deploy.chat.url_label') }}</Label>
        <div class="bg-muted flex items-center justify-between gap-3 rounded-lg p-3">
            <code class="text-foreground truncate text-sm" dir="ltr">{{ url }}</code>
            <div class="flex shrink-0 items-center gap-2">
                <DeployCopyButton :copied="copied" @click="emit('copy')" />
                <Button as-child variant="outline" size="sm" class="gap-2">
                    <a :href="url" target="_blank" rel="noopener"><ExternalLink class="size-4" /> {{ t('agents.deploy.chat.open') }}</a>
                </Button>
            </div>
        </div>
    </div>
</template>
