<script setup lang="ts">
/**
 * Renders an interactive Apache ECharts chart from a raw option spec
 * delivered as a `tool` artifact (kind: 'echarts_option').
 *
 * The renderer is lazy: chart-type and component modules are imported only
 * when an option that needs them lands here (see prepareEcharts). The first
 * chart in a session pays a one-time async-chunk load; subsequent charts
 * resolve instantly from the cache. The initial page bundle ships none of
 * this code.
 *
 * We treat `option` as untrusted (the LLM produced it) — the ECharts runtime
 * is pure JSON-to-canvas, no eval, so it's safe to render directly.
 */
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import VChart, { THEME_KEY } from 'vue-echarts';
import { useI18n } from 'vue-i18n';

import { prepareEcharts } from '@/lib/echartsLazy';

const props = defineProps<{
    /**
     * Raw ECharts `option` object. We don't validate the shape — invalid
     * specs fail gracefully inside ECharts (warns to console, renders empty
     * canvas) rather than crashing the chat.
     */
    option: Record<string, unknown>;
    /** Optional fixed pixel height. Defaults to 320 — fits comfortably in a chat row. */
    height?: number;
}>();

const { t } = useI18n();
const ready = ref(false);

// Match the chart theme to the *actual* applied site theme. useAppearance
// toggles a `dark` class on <html> as the single source of truth — reading
// Inertia's shared prop is unreliable here because some chat surfaces
// (embedded widget, hosted chat) don't go through Inertia. Observe the
// class so the chart re-themes when the user toggles light/dark live.
// In light mode we pass `undefined` so ECharts uses its default styling,
// letting the `bg-card` container's color show through the canvas.
const isDark = ref(false);
let observer: MutationObserver | null = null;

onMounted(async () => {
    await prepareEcharts(props.option);
    isDark.value = document.documentElement.classList.contains('dark');
    observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    ready.value = true;
});

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
});

// If the option is swapped live (e.g. a follow-up tool call replaces the
// artifact), make sure any newly-referenced series types are registered
// before the chart re-renders. prepareEcharts is idempotent via its cache,
// so re-calling is cheap when nothing new is needed.
watch(
    () => props.option,
    async (opt) => {
        await prepareEcharts(opt);
    },
);

const theme = computed<'dark' | undefined>(() => (isDark.value ? 'dark' : undefined));

provide(THEME_KEY, theme);

const heightPx = computed(() => `${props.height ?? 320}px`);
</script>

<template>
    <!-- The container is `not-prose` so the `.prose` wrapper around message
         content doesn't apply paragraph spacing / typography to the chart. -->
    <div class="echarts-block not-prose bg-card my-3 overflow-hidden rounded-md border" :style="{ height: heightPx }">
        <VChart v-if="ready" :option="option" :autoresize="true" class="h-full w-full" />
        <div v-else class="text-muted-foreground flex h-full w-full items-center justify-center text-sm">
            <span class="animate-pulse">{{ t('chat.loading_chart') }}</span>
        </div>
    </div>
</template>

<style scoped>
.echarts-block :deep(canvas) {
    display: block;
}
</style>
