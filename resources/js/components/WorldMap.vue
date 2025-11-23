<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import jsVectorMap from 'jsvectormap';
import 'jsvectormap/dist/maps/world.js';
import axios from 'axios';

interface CountryData {
    country_code: string;
    country_name: string;
    count: number;
    percentage: number;
}

interface Props {
    countryData: CountryData[];
}

const props = defineProps<Props>();
const mapContainer = ref<HTMLElement | null>(null);
let mapInstance: any = null;
let tooltipElement: HTMLElement | null = null;
let isMapInitialized = false;
const allCountries = ref<Record<string, string>>({});

const maxCount = computed(() => Math.max(...props.countryData.map(c => c.count), 1));

// Fetch all country names
const fetchCountryMappings = async () => {
    try {
        const response = await axios.get(route('countries.list'));
        const countries: Record<string, string> = {};
        response.data.forEach((country: { code: string; name: string }) => {
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
        } catch (error) {
            // Silently ignore destruction errors
            console.debug('Map destruction skipped:', error);
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
    props.countryData.forEach(item => {
        values[item.country_code.toUpperCase()] = item.count;
    });

    // Create a map for quick country lookup
    const countryMap: Record<string, CountryData> = {};
    props.countryData.forEach(item => {
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
                fill: '#e5e7eb',
                stroke: '#d1d5db',
                strokeWidth: 0.5,
            },
            hover: {
                fill: '#60a5fa',
                cursor: 'pointer',
            },
        },
        series: {
            regions: [{
                values: values,
                scale: ['#dbeafe', '#3b82f6', '#2563eb', '#1e40af', '#1e3a8a'],
                normalizeFunction: 'polynomial',
                min: 0,
                max: maxCount.value,
            }],
        },
        bindTouchEvents: false,
        onLoaded(map: any) {
            // Add custom tooltip functionality after map loads
            const container = mapContainer.value;
            if (!container) return;

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
                        const countryName = country ? country.country_name : allCountries.value[code] || 'Unknown';
                        const chatCount = country ? country.count : 0;
                        const percentage = country ? Number(country.percentage).toFixed(1) : '0.0';
                        const flagPath = `/images/flags/${code.toLowerCase()}.svg`;

                        tooltipElement.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <img src="${flagPath}" alt="${countryName}" style="width: 20px; height: 20px; border-radius: 2px; object-fit: cover;" />
                                <div style="font-weight: 600;">${countryName}</div>
                            </div>
                            <div style="font-size: 12px; color: #9ca3af; margin-left: 28px;">
                                <span style="color: #3b82f6; font-weight: 600;">${chatCount}</span> chats
                                <span style="margin-left: 8px;">(${percentage}%)</span>
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
        } catch (error) {
            // Silently ignore destruction errors
            console.debug('Map destruction skipped on unmount:', error);
        }
    }
    if (tooltipElement && tooltipElement.parentNode) {
        tooltipElement.parentNode.removeChild(tooltipElement);
    }
    mapInstance = null;
    isMapInitialized = false;
});

watch(() => props.countryData, () => {
    initMap();
}, { deep: true });
</script>

<template>
    <div class="relative overflow-hidden">
        <!-- Map Container -->
        <div ref="mapContainer" style="height: 450px;" class="w-full bg-card overflow-hidden"></div>

        <!-- Color Legend (inside map, positioned left) -->
        <div class="absolute left-4 bottom-4 bg-white dark:bg-gray-800 rounded-lg border shadow-sm p-3 z-10">
            <div class="flex flex-col gap-2 text-xs text-muted-foreground">
                <span class="font-medium">Chat Volume</span>
                <div class="flex items-center gap-2">
                    <span class="text-[10px]">0</span>
                    <div class="flex h-2.5 w-32 rounded overflow-hidden border">
                        <div class="flex-1 bg-[#dbeafe]"></div>
                        <div class="flex-1 bg-[#93c5fd]"></div>
                        <div class="flex-1 bg-[#3b82f6]"></div>
                        <div class="flex-1 bg-[#2563eb]"></div>
                        <div class="flex-1 bg-[#1e40af]"></div>
                        <div class="flex-1 bg-[#1e3a8a]"></div>
                    </div>
                    <span class="text-[10px]">{{ maxCount }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.jvm-tooltip {
    background-color: #1f2937 !important;
    color: #f9fafb !important;
    border-radius: 0.375rem !important;
    padding: 0.5rem 0.75rem !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    border: 1px solid #374151 !important;
    font-size: 0.875rem !important;
    z-index: 9999 !important;
    position: fixed !important;
    pointer-events: none !important;
}

.jvm-zoom-btn {
    position: absolute !important;
    left: 1rem !important;
    background-color: #ffffff !important;
    border: 1px solid #e5e7eb !important;
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
    background-color: #f3f4f6 !important;
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
