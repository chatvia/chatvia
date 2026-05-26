<script setup lang="ts">
/**
 * Renders an interactive Apache ECharts chart from a raw option spec
 * delivered as a `tool` artifact (kind: 'echarts_option').
 *
 * Why a thin wrapper:
 *   - vue-echarts ships ECharts itself, but on-demand registration of
 *     renderers/charts keeps the bundle small. We register canvas +
 *     every chart type the LLM might emit (bar/line/pie/scatter/etc.)
 *     and the standard set of components (title, tooltip, legend, grid,
 *     dataZoom).
 *   - The chart auto-sizes to the bubble; height stays fixed so a long
 *     thread doesn't collapse on tall charts.
 *   - We treat `option` as untrusted (the LLM produced it) — the ECharts
 *     runtime is pure JSON-to-canvas, no eval, so this is safe to render
 *     directly.
 */
import { usePage } from '@inertiajs/vue3';
import {
    BarChart,
    FunnelChart,
    GaugeChart,
    HeatmapChart,
    LineChart,
    PieChart,
    RadarChart,
    ScatterChart,
    SunburstChart,
    TreemapChart,
} from 'echarts/charts';
import {
    DatasetComponent,
    DataZoomComponent,
    GridComponent,
    LegendComponent,
    MarkAreaComponent,
    MarkLineComponent,
    MarkPointComponent,
    PolarComponent,
    TitleComponent,
    ToolboxComponent,
    TooltipComponent,
    TransformComponent,
    VisualMapComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed, provide } from 'vue';
import VChart, { THEME_KEY } from 'vue-echarts';

use([
    CanvasRenderer,
    // Chart series types
    BarChart,
    LineChart,
    PieChart,
    ScatterChart,
    RadarChart,
    GaugeChart,
    HeatmapChart,
    FunnelChart,
    TreemapChart,
    SunburstChart,
    // Components
    TitleComponent,
    TooltipComponent,
    LegendComponent,
    GridComponent,
    DataZoomComponent,
    PolarComponent,
    VisualMapComponent,
    ToolboxComponent,
    MarkLineComponent,
    MarkPointComponent,
    MarkAreaComponent,
    DatasetComponent,
    TransformComponent,
]);

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

// Match the chart theme to the app's dark/light mode so colors look native.
// Inertia stores the theme on the shared `appearance` prop.
const page = usePage<{ appearance?: { theme?: 'dark' | 'light' | 'system' } }>();
const theme = computed<'dark' | 'light'>(() => {
    const t = page.props.appearance?.theme;
    if (t === 'dark') return 'dark';
    if (t === 'light') return 'light';
    // 'system' or undefined → fall through to the OS preference.
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
});

provide(THEME_KEY, theme);

const heightPx = computed(() => `${props.height ?? 320}px`);
</script>

<template>
    <!-- The container is `not-prose` so the `.prose` wrapper around message
         content doesn't apply paragraph spacing / typography to the chart. -->
    <div class="echarts-block not-prose bg-card my-3 overflow-hidden rounded-md border" :style="{ height: heightPx }">
        <VChart :option="option" :autoresize="true" class="h-full w-full" />
    </div>
</template>

<style scoped>
.echarts-block :deep(canvas) {
    display: block;
}
</style>
