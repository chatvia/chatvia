// Ambient declarations for the jsvectormap package and its map data files.
// jsvectormap ships ESM without bundled .d.ts files; we model it as a loose
// constructor + the few methods our WorldMap.vue actually calls.
declare module 'jsvectormap' {
    interface JsVectorMapOptions {
        selector: HTMLElement | string;
        map: string;
        backgroundColor?: string;
        regionStyle?: Record<string, unknown>;
        markerStyle?: Record<string, unknown>;
        markers?: Array<{ name: string; coords: [number, number] }>;
        series?: Record<string, unknown>;
        onRegionTipShow?: (event: Event, label: { html: (value: string) => void }, code: string) => void;
        [key: string]: unknown;
    }

    class JsVectorMap {
        constructor(options: JsVectorMapOptions);
        destroy(): void;
        updateSize(): void;
        series?: { regions?: Array<{ setValues: (values: Record<string, number>) => void }> };
        [key: string]: unknown;
    }

    export default JsVectorMap;
}

declare module 'jsvectormap/dist/maps/world.js' {
    const world: unknown;
    export default world;
}
