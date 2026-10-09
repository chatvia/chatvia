<script setup lang="ts">
// A read-only sign-up link with a copy button. The button shows a check mark
// for two seconds after a copy so the owner knows it worked.
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    url: string;
}>();

const { t } = useI18n();
const { copy, copied } = useClipboard({ legacy: true, copiedDuring: 2000 });
</script>

<template>
    <div class="flex items-center gap-2">
        <Input :model-value="url" readonly class="font-mono text-xs" />
        <Button
            type="button"
            variant="outline"
            size="icon"
            :title="t('workspace.common.copy')"
            :aria-label="t('workspace.common.copy')"
            @click="copy(url)"
        >
            <Check v-if="copied" class="h-4 w-4 text-green-600" />
            <Copy v-else class="h-4 w-4" />
        </Button>
    </div>
</template>
