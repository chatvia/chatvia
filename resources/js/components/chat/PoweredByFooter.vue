<script setup lang="ts">
import { useBrand } from '@/composables/useBrand';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * The small "Powered by …" credit under the hosted chat. The label and link
 * come from `config('chatvia.brand.powered_by')`: the credit is hidden when the
 * install turns it off, and shows as plain text when no link is set.
 */
const props = defineProps<{
    // Per-agent switch: false when the workspace owns the remove-branding add-on.
    show: boolean;
}>();

const { t } = useI18n();
const { brand } = useBrand();

const visible = computed(() => props.show && brand.value.powered_by.enabled);
</script>

<template>
    <div v-if="visible" class="mt-2 flex items-center justify-center">
        <component
            :is="brand.powered_by.url ? 'a' : 'span'"
            :href="brand.powered_by.url ?? undefined"
            :target="brand.powered_by.url ? '_blank' : undefined"
            :rel="brand.powered_by.url ? 'noopener noreferrer' : undefined"
            class="text-muted-foreground/50 inline-flex items-center gap-1 text-[11px] transition-colors"
            :class="{ 'hover:text-muted-foreground/80': brand.powered_by.url }"
        >
            <!-- Decorative: the label next to it already names the product. -->
            <img src="/images/logo.svg" alt="" class="h-3.5 w-3.5 opacity-70" />
            {{ t('chat.powered_by', { label: brand.powered_by.label }) }}
        </component>
    </div>
</template>
