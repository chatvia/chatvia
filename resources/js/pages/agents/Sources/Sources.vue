<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';
import WebComponent from './WebComponent.vue';
import TextComponent from './TextComponent.vue';
import YoutubeComponent from './YoutubeComponent.vue';
import QaComponent from './QaComponent.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import axios from 'axios';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
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
        id: 'files',
        label: 'Files',
        icon: 'pi-file',
        description: 'Upload and manage documents (PDF, Word, etc.)',
        route: 'agents.sources.files',
        statsKey: 'files'
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
        route: 'agents.sources.youtube',
        statsKey: 'youtube'
    },
    {
        id: 'qa',
        label: 'Q&A',
        icon: 'pi-question-circle',
        description: 'Craft responses for key questions',
        route: 'agents.sources.qa',
        statsKey: 'qa'
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
    if (currentRoute.includes('/sources/youtube')) {
        return 'youtube';
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
    if (currentRoute.includes('/sources/qa')) {
        return 'qa';
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
        case 'youtube':
            return YoutubeComponent;
        case 'qa':
            return QaComponent;
        default:
            return Files;
    }
});

// Handle content type selection
const selectContentType = (typeId: string) => {
    const contentType = contentTypes.find(type => type.id === typeId);

    if (contentType?.route) {
        // Navigate to the appropriate route
        router.visit(route(contentType.route, { agent: props.agent.external_id }));
    } else {
        // For not-yet-implemented features, just update local state
        selectedContentType.value = typeId;
    }
};

// Fetch stats from API
const fetchStats = async () => {
    try {
        isLoadingStats.value = true;
        const response = await axios.get(route('agents.sources.stats', { agent: props.agent.external_id }));
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
        <div class="flex justify-center">
            <div class="w-full max-w-6xl min-h-[calc(100vh-220px)]">
                <!-- Dynamic Component Based on Selection -->
                <component
                    :is="currentComponent"
                    :agent="agent"
                    :file-type="selectedContentType"
                    v-if="selectedContentType === 'pdf' || selectedContentType === 'word' || selectedContentType === 'markdown' || selectedContentType === 'audio' || selectedContentType === 'video' || selectedContentType === 'web' || selectedContentType === 'text' || selectedContentType === 'youtube' || selectedContentType === 'qa'"
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
