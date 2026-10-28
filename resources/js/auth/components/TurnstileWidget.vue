<script setup lang="ts">
/**
 * Cloudflare Turnstile, the bot check of the public forms (Settings →
 * Security). Renders the widget for the page's site key and keeps the one-use
 * token it hands out in `v-model`; the form sends it as
 * `cf-turnstile-response`. Call `reset()` after a failed submit: a token
 * counts once.
 */
import { loadTurnstile, type TurnstileApi } from '@/auth/lib/turnstile';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ siteKey: string }>();
const token = defineModel<string>({ default: '' });

const { locale } = useI18n();
const container = ref<HTMLElement | null>(null);
let turnstile: TurnstileApi | null = null;
let widgetId: string | null = null;

onMounted(async () => {
    turnstile = await loadTurnstile();

    if (!container.value) {
        return;
    }

    widgetId = turnstile.render(container.value, {
        sitekey: props.siteKey,
        language: locale.value,
        theme: 'auto',
        callback: (value: string) => (token.value = value),
        'expired-callback': () => (token.value = ''),
        'error-callback': () => (token.value = ''),
    });
});

onBeforeUnmount(() => {
    if (widgetId !== null) {
        turnstile?.remove(widgetId);
    }
});

function reset(): void {
    token.value = '';

    if (widgetId !== null) {
        turnstile?.reset(widgetId);
    }
}

defineExpose({ reset });
</script>

<template>
    <div ref="container" class="flex min-h-[65px] justify-center" />
</template>
