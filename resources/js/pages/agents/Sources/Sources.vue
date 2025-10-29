<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';
import WebComponent from './WebComponent.vue';
import TextComponent from './TextComponent.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import axios from 'axios';

interface Props {
    agent: any;
}

interface SourceStats {
    websites: number;
    texts: number;
    pdf: number;
    word: number;
    markdown: number;
    audio: number;
    [key: string]: number;
}

const props = defineProps<Props>();
const page = usePage();
const stats = ref<SourceStats | null>(null);
const isLoadingStats = ref(true);

// Knowledge base content types
const contentTypes = [
    {
        id: 'pdf',
        label: 'PDF',
        icon: 'pi-file-pdf',
        description: 'Upload and manage PDF documents',
        route: 'agents.sources.pdf',
        statsKey: 'pdf'
    },
    {
        id: 'word',
        label: 'Word',
        icon: 'pi-file-word',
        description: 'Upload and manage Word documents',
        route: 'agents.sources.word',
        statsKey: 'word'
    },
    {
        id: 'markdown',
        label: 'Markdown',
        icon: 'pi-file-edit',
        description: 'Upload and manage Markdown files',
        route: 'agents.sources.markdown',
        statsKey: 'markdown'
    },
    {
        id: 'text',
        label: 'Text',
        icon: 'pi-align-justify',
        description: 'Add custom text content and notes directly',
        route: 'agents.sources.text',
        statsKey: 'texts'
    },
    {
        id: 'web',
        label: 'Website',
        icon: 'pi-globe',
        description: 'Crawl websites and web pages for knowledge',
        route: 'agents.sources.web',
        statsKey: 'websites'
    },
    {
        id: 'audio',
        label: 'Audio',
        icon: 'pi-volume-up',
        description: 'Upload audio files and podcasts for transcription',
        route: 'agents.sources.audio',
        statsKey: 'audio'
    },
    {
        id: 'video',
        label: 'Video',
        icon: 'pi-video',
        description: 'Upload video files for transcription and analysis',
        route: 'agents.sources.video',
        statsKey: 'video'
    },
    {
        id: 'youtube',
        label: 'YouTube',
        icon: 'pi-youtube',
        description: 'Import YouTube videos and extract transcripts',
        route: null, // Will be implemented later
        statsKey: null
    },
];

const getCurrentType = () => {
    const currentRoute = page.url;

    if (currentRoute.includes('/sources/web')) {
        return 'web';
    }
    if (currentRoute.includes('/sources/text')) {
        return 'text';
    }
    if (currentRoute.includes('/sources/pdf')) {
        return 'pdf';
    }
    if (currentRoute.includes('/sources/word')) {
        return 'word';
    }
    if (currentRoute.includes('/sources/markdown')) {
        return 'markdown';
    }
    if (currentRoute.includes('/sources/audio')) {
        return 'audio';
    }
    if (currentRoute.includes('/sources/video')) {
        return 'video';
    }
    return 'pdf'; // default to PDF
};

// Current selected content type
const selectedContentType = ref(getCurrentType());

// Computed property for current content component
const currentComponent = computed(() => {
    switch (selectedContentType.value) {
        case 'pdf':
        case 'word':
        case 'markdown':
        case 'audio':
        case 'video':
            return Files;
        case 'web':
            return WebComponent;
        case 'text':
            return TextComponent;
        default:
            return Files;
    }
});

// Handle content type selection
const selectContentType = (typeId: string) => {
    const contentType = contentTypes.find(type => type.id === typeId);

    if (contentType?.route) {
        // Navigate to the appropriate route
        router.visit(route(contentType.route, { agent: props.agent.id }));
    } else {
        // For not-yet-implemented features, just update local state
        selectedContentType.value = typeId;
    }
};

// Fetch stats from API
const fetchStats = async () => {
    try {
        isLoadingStats.value = true;
        const response = await axios.get(route('agents.sources.stats', { agent: props.agent.id }));
        stats.value = response.data;
    } catch (error) {
        console.error('Failed to fetch source stats:', error);
    } finally {
        isLoadingStats.value = false;
    }
};

// Get stat count for a content type
const getStatCount = (statsKey: string | null): number | null => {
    if (!statsKey || !stats.value || !(statsKey in stats.value)) {
        return null;
    }
    return stats.value[statsKey] || 0;
};

// Format label with count
const formatLabel = (contentType: any): string => {
    if (isLoadingStats.value) {
        return contentType.label;
    }
    const count = getStatCount(contentType.statsKey);
    if (count !== null) {
        return `${contentType.label} (${count})`;
    }
    return contentType.label;
};

// Update selected type when component mounts or route changes
onMounted(() => {
    selectedContentType.value = getCurrentType();
    fetchStats();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Sources">
        <div class="grid lg:grid-cols-12 gap-6 min-h-[calc(100vh-220px)]">
            <!-- Content Types Sidebar -->
            <div class="lg:col-span-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-sm font-medium flex items-center gap-2">
                            <i class="pi pi-database h-4 w-4" />
                            Sources
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <!-- Two column grid for content types -->
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                v-for="contentType in contentTypes"
                                :key="contentType.id"
                                @click="selectContentType(contentType.id)"
                                :class="[
                                    'group relative p-4 rounded-lg border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 cursor-pointer',
                                    selectedContentType === contentType.id
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary'
                                        : 'border-border bg-background'
                                ]"
                            >
                                <div class="flex flex-col items-center text-center space-y-3">
                                    <div :class="[
                                        'flex-shrink-0 rounded-lg p-3 transition-colors',
                                        selectedContentType === contentType.id
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary'
                                    ]">
                                        <i :class="`pi ${contentType.icon} text-2xl`" />
                                    </div>
                                    <h3 class="font-medium text-sm">{{ formatLabel(contentType) }}</h3>
                                </div>

                                <!-- Selected indicator -->
                                <div
                                    v-if="selectedContentType === contentType.id"
                                    class="absolute top-2 right-2 h-2 w-2 rounded-full bg-primary"
                                ></div>
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Content Area -->
            <div class="lg:col-span-9">
                <!-- Dynamic Component Based on Selection (no extra container) -->
                <component
                    :is="currentComponent"
                    :agent="agent"
                    :file-type="selectedContentType"
                    v-if="selectedContentType === 'pdf' || selectedContentType === 'word' || selectedContentType === 'markdown' || selectedContentType === 'audio' || selectedContentType === 'video' || selectedContentType === 'web' || selectedContentType === 'text'"
                />

                <!-- Placeholder for other content types -->
                <Card
                    v-else
                    class="h-full min-h-[400px]"
                >
                    <CardContent class="flex items-center justify-center h-full p-8">
                        <div class="text-center">
                            <i
                                :class="`pi ${contentTypes.find(t => t.id === selectedContentType)?.icon} text-6xl mx-auto mb-4 text-muted-foreground`"
                            />
                            <h3 class="text-lg font-medium mb-2">
                                {{ contentTypes.find(t => t.id === selectedContentType)?.label }} Coming Soon
                            </h3>
                            <p class="text-muted-foreground max-w-md">
                                {{ contentTypes.find(t => t.id === selectedContentType)?.description }}
                                This feature is currently under development.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
