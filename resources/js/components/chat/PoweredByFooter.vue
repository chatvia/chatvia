<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useBrand } from '@/composables/useBrand';
import { useI18n } from 'vue-i18n';

/**
 * The small "Powered by …" credit under the hosted chat. It always links to
 * chatvia.ai (fixed here, not a setting); the label comes from
 * `config('chatvia.brand.powered_by')`, and the credit is hidden only when
 * the install hides it with a license.
 */
const { t } = useI18n();
const { brand } = useBrand();
</script>

<template>
    <div v-if="brand.powered_by.enabled" class="mt-2 flex items-center justify-center">
        <a
            href="https://chatvia.ai"
            target="_blank"
            rel="noopener"
            class="text-muted-foreground/50 hover:text-muted-foreground/80 inline-flex items-center gap-1 text-[11px] transition-colors"
        >
            <!-- Decorative: the label next to it already names the product. -->
            <AppLogoIcon aria-hidden="true" class="h-3.5 w-3.5" />
            {{ t('chat.powered_by', { label: brand.powered_by.label }) }}
        </a>
    </div>
</template>
