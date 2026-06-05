/**
 * Lazy ECharts registration.
 *
 * ECharts is registered globally via `use([...])` — once a series type is
 * registered, every <VChart> in the app can render it. We exploit that by
 * deferring imports until we actually see an option that needs them: a chat
 * thread that only ever renders bar charts never downloads the sunburst,
 * sankey, or candlestick bundles.
 *
 * Vite bundles each `import('echarts/charts')` call into the same async
 * chunk, so the first chart render pulls one ~250KB chunk; subsequent
 * charts are instant because the chunk is cached. The renderer (canvas) is
 * eagerly imported because every chart needs it.
 */
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';

use([CanvasRenderer]);

type Loader = () => Promise<unknown>;

const SERIES_LOADERS: Record<string, Loader> = {
    bar: () => import('echarts/charts').then((m) => m.BarChart),
    line: () => import('echarts/charts').then((m) => m.LineChart),
    pie: () => import('echarts/charts').then((m) => m.PieChart),
    scatter: () => import('echarts/charts').then((m) => m.ScatterChart),
    radar: () => import('echarts/charts').then((m) => m.RadarChart),
    heatmap: () => import('echarts/charts').then((m) => m.HeatmapChart),
    gauge: () => import('echarts/charts').then((m) => m.GaugeChart),
    sankey: () => import('echarts/charts').then((m) => m.SankeyChart),
    treemap: () => import('echarts/charts').then((m) => m.TreemapChart),
    sunburst: () => import('echarts/charts').then((m) => m.SunburstChart),
    candlestick: () => import('echarts/charts').then((m) => m.CandlestickChart),
    funnel: () => import('echarts/charts').then((m) => m.FunnelChart),
};

const COMPONENT_LOADERS: Record<string, Loader> = {
    title: () => import('echarts/components').then((m) => m.TitleComponent),
    tooltip: () => import('echarts/components').then((m) => m.TooltipComponent),
    legend: () => import('echarts/components').then((m) => m.LegendComponent),
    grid: () => import('echarts/components').then((m) => m.GridComponent),
    dataZoom: () => import('echarts/components').then((m) => m.DataZoomComponent),
    visualMap: () => import('echarts/components').then((m) => m.VisualMapComponent),
    polar: () => import('echarts/components').then((m) => m.PolarComponent),
    toolbox: () => import('echarts/components').then((m) => m.ToolboxComponent),
    dataset: () => import('echarts/components').then((m) => m.DatasetComponent),
    markArea: () => import('echarts/components').then((m) => m.MarkAreaComponent),
    markLine: () => import('echarts/components').then((m) => m.MarkLineComponent),
    markPoint: () => import('echarts/components').then((m) => m.MarkPointComponent),
};

// Promise cache so concurrent mounts don't double-import and idle mounts
// resolve instantly from the same in-flight promise.
const cache = new Map<string, Promise<void>>();

function load(key: string, loader: Loader): Promise<void> {
    let p = cache.get(key);
    if (!p) {
        p = loader().then((mod) => {
            use([mod as Extract<Parameters<typeof use>[0], readonly unknown[]>[number]]);
        });
        cache.set(key, p);
    }
    return p;
}

/**
 * Inspect an ECharts option and register every series type + component it
 * references. Resolves when all relevant modules are loaded — the caller
 * can then mount <VChart> safely. Idempotent across calls and across
 * concurrent mounts.
 */
export async function prepareEcharts(option: Record<string, unknown>): Promise<void> {
    const tasks: Promise<void>[] = [];

    // Universal small components — almost every chart sets at least
    // title/tooltip/legend/grid, so register them up-front to avoid a
    // per-key option lookup for the common case.
    for (const key of ['title', 'tooltip', 'legend', 'grid'] as const) {
        tasks.push(load(`c:${key}`, COMPONENT_LOADERS[key]));
    }

    // Series types from option.series. ECharts allows series to be either
    // an array or a single object — normalize before reading.
    const seriesRaw = option.series;
    const seriesList: Array<Record<string, unknown>> = Array.isArray(seriesRaw)
        ? (seriesRaw as Array<Record<string, unknown>>)
        : seriesRaw && typeof seriesRaw === 'object'
          ? [seriesRaw as Record<string, unknown>]
          : [];

    const seenTypes = new Set<string>();
    for (const s of seriesList) {
        const t = s?.type;
        if (typeof t === 'string') seenTypes.add(t);
    }
    for (const type of seenTypes) {
        const loader = SERIES_LOADERS[type];
        if (loader) tasks.push(load(`s:${type}`, loader));
    }

    // Conditional components — present in `option` only when needed.
    for (const key of ['dataZoom', 'visualMap', 'polar', 'toolbox', 'dataset'] as const) {
        if (option[key]) tasks.push(load(`c:${key}`, COMPONENT_LOADERS[key]));
    }

    await Promise.all(tasks);
}
