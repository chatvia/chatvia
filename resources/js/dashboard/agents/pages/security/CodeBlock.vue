<script setup lang="ts">
// A highlighted code sample with a "Copy" button in its top corner. `html` is
// highlight.js output built from our own sample code, never from user input.
import { Button } from '@/shared/ui/button';
import { Check, Copy } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    html: string;
    language: string;
    copied: boolean;
}>();

const emit = defineEmits<{ copy: [] }>();

const { t } = useI18n();
</script>

<template>
    <div class="bg-muted relative rounded-lg p-4">
        <Button type="button" variant="outline" size="sm" class="absolute end-3 top-3 gap-2" @click="emit('copy')">
            <Check v-if="copied" class="size-4 text-green-600" />
            <Copy v-else class="size-4" />
            {{ t('agents.identity_verification.copy') }}
        </Button>
        <pre class="hljs overflow-x-auto bg-transparent p-0 text-xs"><code :class="`language-${language}`" v-html="html" /></pre>
    </div>
</template>
