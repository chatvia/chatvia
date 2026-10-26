<script setup lang="ts">
/**
 * World map of chats per country (jsvectormap), shaded by chat count, with a
 * custom hover tooltip (flag, name, chats, share). Countries without chats
 * still get their translated name from the countries list. Every color comes
 * from CSS variables (`--map-*`), so the map follows light/dark mode without
 * being redrawn: jsvectormap writes `fill` attributes, which CSS overrides.
 */
import { fetchCountryNames } from '@/api/analytics';
import jsVectorMap from 'jsvectormap';
import 'jsvectormap/dist/maps/world.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type CountryData = Domain.Agent.Data.Resources.Stats.CountryUsageData;

interface Props {
    countryData: CountryData[];
}

const props = defineProps<Props>();
const mapContainer = ref<HTMLElement | null>(null);
const { t } = useI18n();
let mapInstance: jsVectorMap | null = null;
let tooltipElement: HTMLElement | null = null;
let isMapInitialized = false;
const allCountries = ref<Record<string, string>>({});

const maxCount = computed(() => Math.max(...props.countryData.map((c) => c.count), 1));

const levelCount = 5;
const levels = Array.from({ length: levelCount }, (_, index) => index + 1);

// Square-root scale so a few busy countries don't wash out the rest.
const levelFor = (count: number): number => Math.max(1, Math.ceil(Math.sqrt(count / maxCount.value) * levelCount));

// Fetch all country names
const fetchCountryMappings = async () => {
    try {
        const countryNames = await fetchCountryNames();
        const countries: Record<string, string> = {};
        countryNames.forEach((country) => {
            countries[country.code.toUpperCase()] = country.name;
        });
        allCountries.value = countries;
    } catch (error) {
        console.error('Failed to fetch country mappings:', error);
    }
};

const initMap = () => {
    if (!mapContainer.value) return;

    // Clear existing map only if it was fully initialized
    if (mapInstance && isMapInitialized) {
        try {
            mapInstance.destroy();
        } catch {
            // A half-torn-down map can throw here; it is replaced right below anyway.
        }
        mapInstance = null;
        isMapInitialized = false;
    }

    // Remove old tooltip if it exists
    if (tooltipElement && tooltipElement.parentNode) {
        tooltipElement.parentNode.removeChild(tooltipElement);
        tooltipElement = null;
    }

    // Clear the container to prevent duplication
    if (mapContainer.value) {
        mapContainer.value.innerHTML = '';
    }

    // Prepare data for the map
    const values: Record<string, number> = {};
    props.countryData.forEach((item) => {
        values[item.country_code.toUpperCase()] = item.count;
    });

    // Create a map for quick country lookup
    const countryMap: Record<string, CountryData> = {};
    props.countryData.forEach((item) => {
        countryMap[item.country_code.toUpperCase()] = item;
    });

    // Create map
    mapInstance = new jsVectorMap({
        selector: mapContainer.value,
        map: 'world',
        backgroundColor: 'transparent',
        zoomButtons: true,
        zoomOnScroll: false,
        showTooltip: false,
        regionStyle: {
            initial: {
                strokeWidth: 0.5,
            },
            hover: {
                cursor: 'pointer',
            },
        },
        bindTouchEvents: false,
        onLoaded() {
            // Add custom tooltip functionality after map loads
            const container = mapContainer.value;
            if (!container) return;

            container.querySelectorAll<SVGPathElement>('path[data-code]').forEach((path) => {
                const count = values[path.getAttribute('data-code')?.toUpperCase() ?? ''];
                if (count) {
                    path.style.setProperty('--region-fill', `var(--map-level-${levelFor(count)})`);
                }
            });

            tooltipElement = document.createElement('div');
            tooltipElement.className = 'jvm-tooltip';
            tooltipElement.style.display = 'none';
            tooltipElement.style.position = 'fixed';
            tooltipElement.style.pointerEvents = 'none';
            document.body.appendChild(tooltipElement);

            container.addEventListener('mouseover', (e: MouseEvent) => {
                const target = e.target as SVGElement;
                if (target.tagName === 'path' && target.getAttribute('data-code')) {
                    const code = target.getAttribute('data-code')?.toUpperCase();

                    if (code && tooltipElement) {
                        // Check if country has data, otherwise use 0
                        const country = countryMap[code];
                        const countryName = country ? country.country_name : allCountries.value[code] || t('shared.unknown');
                        const chatCount = country ? country.count : 0;
                        const percentage = country ? Number(country.percentage).toFixed(1) : '0.0';
                        const flagPath = `/images/flags/${code.toLowerCase()}.svg`;

                        tooltipElement.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <img src="${flagPath}" alt="${countryName}" style="width: 20px; height: 20px; border-radius: 2px; object-fit: cover;" />
                                <div style="font-weight: 600;">${countryName}</div>
                            </div>
                            <div style="font-size: 12px; color: var(--muted-foreground); margin-inline-start: 28px;">
                                <span style="color: var(--map-accent); font-weight: 600;">${chatCount}</span> ${t('shared.chats')}
                                <span style="margin-inline-start: 8px;">(${percentage}%)</span>
                            </div>
                        `;
                        tooltipElement.style.display = 'block';
                    }
                }
            });

            container.addEventListener('mousemove', (e: MouseEvent) => {
                const target = e.target as SVGElement;
                if (target.tagName === 'path' && target.getAttribute('data-code') && tooltipElement) {
                    tooltipElement.style.left = `${e.clientX + 10}px`;
                    tooltipElement.style.top = `${e.clientY + 10}px`;
                }
            });

            container.addEventListener('mouseout', (e: MouseEvent) => {
                const target = e.target as SVGElement;
                if (target.tagName === 'path' && tooltipElement) {
                    tooltipElement.style.display = 'none';
                }
            });

            // Mark map as fully initialized
            isMapInitialized = true;
        },
    });
};

onMounted(async () => {
    await fetchCountryMappings();
    initMap();
});

onBeforeUnmount(() => {
    if (mapInstance && isMapInitialized) {
        try {
            mapInstance.destroy();
        } catch {
            // Nothing to clean up if the library fails here; the element is going away.
        }
    }
    if (tooltipElement && tooltipElement.parentNode) {
        tooltipElement.parentNode.removeChild(tooltipElement);
    }
    mapInstance = null;
    isMapInitialized = false;
});

watch(
    () => props.countryData,
    () => {
        initMap();
    },
    { deep: true },
);
</script>

<template>
    <div class="world-map relative overflow-hidden">
        <!-- Map Container -->
        <div ref="mapContainer" style="height: 450px" class="bg-card w-full overflow-hidden"></div>

        <!-- Color Legend (inside map, positioned left) -->
        <div class="bg-card absolute start-4 bottom-4 z-10 rounded-lg border p-3 shadow-sm">
            <div class="text-muted-foreground flex flex-col gap-2 text-xs">
                <span class="font-medium">{{ t('shared.chat_volume') }}</span>
                <div class="flex items-center gap-2">
                    <span class="text-[10px]">0</span>
                    <div class="flex h-2.5 w-32 overflow-hidden rounded border">
                        <div v-for="level in levels" :key="level" class="flex-1" :style="{ background: `var(--map-level-${level})` }"></div>
                    </div>
                    <span class="text-[10px]">{{ maxCount }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* The tooltip lives on <body>, so the map tokens sit on :root rather than on .world-map. */
:root {
    --map-land: #e5e7eb;
    --map-hover: #60a5fa;
    --map-accent: #2563eb;
    --map-level-1: #bfdbfe;
    --map-level-2: #93c5fd;
    --map-level-3: #3b82f6;
    --map-level-4: #1d4ed8;
    --map-level-5: #1e3a8a;
}

.dark {
    --map-land: hsl(0 0% 22%);
    --map-hover: #93c5fd;
    --map-accent: #60a5fa;
    --map-level-1: #1e3a8a;
    --map-level-2: #1d4ed8;
    --map-level-3: #3b82f6;
    --map-level-4: #60a5fa;
    --map-level-5: #bfdbfe;
}

.world-map .jvm-region {
    fill: var(--region-fill, var(--map-land));
    stroke: var(--card);
    transition: fill 0.15s ease;
}

.world-map .jvm-region:hover {
    fill: var(--map-hover);
}

.jvm-tooltip {
    background-color: var(--popover) !important;
    color: var(--foreground) !important;
    border-radius: 0.375rem !important;
    padding: 0.5rem 0.75rem !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    border: 1px solid var(--border) !important;
    font-size: 0.875rem !important;
    z-index: 9999 !important;
    position: fixed !important;
    pointer-events: none !important;
}

.jvm-zoom-btn {
    position: absolute !important;
    inset-inline-start: 1rem !important;
    background-color: var(--card) !important;
    color: var(--foreground) !important;
    border: 1px solid var(--border) !important;
    border-radius: 0.375rem !important;
    width: 2rem !important;
    height: 2rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1) !important;
    cursor: pointer !important;
}

.jvm-zoom-btn.jvm-zoomin {
    top: 1rem !important;
}

.jvm-zoom-btn.jvm-zoomout {
    top: 3.5rem !important;
}

.jvm-zoom-btn:hover {
    background-color: var(--muted) !important;
}

.jvm-container {
    position: relative !important;
    overflow: hidden !important;
    width: 100% !important;
}

.jvm-container svg {
    max-width: 100% !important;
    height: auto !important;
}

/* Hide the default jsvectormap tooltip (library creates it with different class) */
.jvm-tip {
    display: none !important;
}
</style>
