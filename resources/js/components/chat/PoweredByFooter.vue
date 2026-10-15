<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useBrand } from '@/composables/useBrand';
import { useI18n } from 'vue-i18n';

/**
 * The small "Powered by …" credit under the hosted chat. The label and link
 * come from `config('chatvia.brand.powered_by')`: the credit is hidden when the
 * install turns it off (for every agent), and shows as plain text when no
 * link is set.
 */
const { t } = useI18n();
const { brand } = useBrand();
</script>

<template>
    <div v-if="brand.powered_by.enabled" class="mt-2 flex items-center justify-center">
        <component
            :is="brand.powered_by.url ? 'a' : 'span'"
            :href="brand.powered_by.url ?? undefined"
            :target="brand.powered_by.url ? '_blank' : undefined"
            :rel="brand.powered_by.url ? 'noopener noreferrer' : undefined"
            class="text-muted-foreground/50 inline-flex items-center gap-1 text-[11px] transition-colors"
            :class="{ 'hover:text-muted-foreground/80': brand.powered_by.url }"
        >
            <!-- Decorative: the label next to it already names the product. -->
            <AppLogoIcon aria-hidden="true" class="h-3.5 w-3.5" />
            {{ t('chat.powered_by', { label: brand.powered_by.label }) }}
        </component>
    </div>
</template>
