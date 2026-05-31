<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    siteKey: string;
    modelValue?: string;
    theme?: 'light' | 'dark' | 'auto';
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const widgetRef = ref<HTMLElement | null>(null);
const widgetId = ref<string | null>(null);

// Unique global callback names per instance, since the Turnstile JS calls
// global functions by name (`data-callback="..."`) instead of accepting a JS
// reference.
const callbackName = computed(() => `__turnstile_cb_${Math.random().toString(36).slice(2)}`);
const expiredName = computed(() => `__turnstile_exp_${Math.random().toString(36).slice(2)}`);

declare global {
    interface Window {
        turnstile?: {
            render: (el: HTMLElement | string, opts: Record<string, unknown>) => string;
            reset: (id?: string) => void;
            remove: (id: string) => void;
        };
    }
}

const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

const loadScript = (): Promise<void> =>
    new Promise((resolve, reject) => {
        if (window.turnstile) {
            resolve();
            return;
        }
        const existing = document.querySelector(`script[src^="${SCRIPT_SRC}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Turnstile script failed to load.')));
            return;
        }
        const script = document.createElement('script');
        script.src = SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.addEventListener('load', () => resolve());
        script.addEventListener('error', () => reject(new Error('Turnstile script failed to load.')));
        document.head.appendChild(script);
    });

const renderWidget = () => {
    if (!widgetRef.value || !window.turnstile) return;
    widgetId.value = window.turnstile.render(widgetRef.value, {
        sitekey: props.siteKey,
        theme: props.theme ?? 'auto',
        callback: callbackName.value,
        'expired-callback': expiredName.value,
        'error-callback': expiredName.value,
        'timeout-callback': expiredName.value,
    });
};

onMounted(async () => {
    (window as unknown as Record<string, unknown>)[callbackName.value] = (token: string) => {
        emit('update:modelValue', token);
    };
    (window as unknown as Record<string, unknown>)[expiredName.value] = () => {
        emit('update:modelValue', '');
    };

    try {
        await loadScript();
        renderWidget();
    } catch (e) {
        console.error(e);
    }
});

onBeforeUnmount(() => {
    if (widgetId.value && window.turnstile) {
        try {
            window.turnstile.remove(widgetId.value);
        } catch {
            // ignore
        }
    }
    delete (window as unknown as Record<string, unknown>)[callbackName.value];
    delete (window as unknown as Record<string, unknown>)[expiredName.value];
});

defineExpose({
    reset() {
        if (widgetId.value && window.turnstile) {
            window.turnstile.reset(widgetId.value);
        }
    },
});
</script>

<template>
    <div ref="widgetRef" />
</template>
