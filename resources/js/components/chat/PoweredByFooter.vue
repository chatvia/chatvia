<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * The small "Powered by ChatVia.ai" credit under the hosted chat, linked to
 * chatvia.ai. It is hidden only when the install hides it with a license
 * (`config('chatvia.powered_by')`, shared as `poweredBy`).
 */
const { t } = useI18n();
const page = usePage<SharedData>();
</script>

<template>
    <div v-if="page.props.poweredBy === true" class="mt-2 flex items-center justify-center">
        <a
            href="https://chatvia.ai"
            target="_blank"
            rel="noopener"
            class="text-muted-foreground/50 hover:text-muted-foreground/80 inline-flex items-center gap-1 text-[11px] transition-colors"
        >
            <!-- Decorative: the label next to it already names the product. -->
            <AppLogoIcon aria-hidden="true" class="h-3.5 w-3.5" />
            {{ t('chat.powered_by', { label: 'ChatVia.ai' }) }}
        </a>
    </div>
</template>
