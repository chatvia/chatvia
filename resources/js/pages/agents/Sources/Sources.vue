<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { useFeature } from '@/composables/useFeature';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import Files from './Files.vue';
import QaComponent from './QaComponent.vue';
import TextComponent from './TextComponent.vue';
import WebComponent from './WebComponent.vue';
import YoutubeComponent from './YoutubeComponent.vue';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
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
const { t } = useI18n();
const { hasFeature } = useFeature();
const stats = ref<SourceStats | null>(null);
const isLoadingStats = ref(true);

// Knowledge base content types. YouTube sources are unfinished and only show
// when the install turns them on (FEATURE_YOUTUBE_SOURCES).
const contentTypes = [
    {
        id: 'files',
        label: 'agents.sources.overview.files.label',
        icon: 'pi-file',
        description: 'agents.sources.overview.files.description',
        route: 'agents.sources.files',
        statsKey: 'files',
    },
    {
        id: 'text',
        label: 'agents.sources.overview.text.label',
        icon: 'pi-align-justify',
        description: 'agents.sources.overview.text.description',
        route: 'agents.sources.text.index',
        statsKey: 'texts',
    },
    {
        id: 'web',
        label: 'agents.sources.overview.web.label',
        icon: 'pi-globe',
        description: 'agents.sources.overview.web.description',
        route: 'agents.sources.web',
        statsKey: 'websites',
    },
    {
        id: 'audio',
        label: 'agents.sources.overview.audio.label',
        icon: 'pi-volume-up',
        description: 'agents.sources.overview.audio.description',
        route: 'agents.sources.audio',
        statsKey: 'audio',
    },
    {
        id: 'video',
        label: 'agents.sources.overview.video.label',
        icon: 'pi-video',
        description: 'agents.sources.overview.video.description',
        route: 'agents.sources.video',
        statsKey: 'video',
    },
    {
        id: 'youtube',
        label: 'agents.sources.overview.youtube.label',
        icon: 'pi-youtube',
        description: 'agents.sources.overview.youtube.description',
        route: 'agents.sources.youtube.index',
        statsKey: 'youtube',
    },
    {
        id: 'qa',
        label: 'agents.sources.overview.qa.label',
        icon: 'pi-question-circle',
        description: 'agents.sources.overview.qa.description',
        route: 'agents.sources.qa.index',
        statsKey: 'qa',
    },
].filter((type) => type.id !== 'youtube' || hasFeature('youtube_sources'));

const getCurrentType = () => {
    const currentRoute = page.url;

    if (currentRoute.includes('/sources/web')) {
        return 'web';
    }
    if (currentRoute.includes('/sources/text')) {
        return 'text';
    }
    if (hasFeature('youtube_sources') && currentRoute.includes('/sources/youtube')) {
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
const _selectContentType = (typeId: string) => {
    const contentType = contentTypes.find((type) => type.id === typeId);

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
const _formatLabel = (contentType: any): string => {
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
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.sources')">
        <div class="flex justify-center">
            <div class="min-h-[calc(100vh-220px)] w-full max-w-6xl">
                <!-- Dynamic Component Based on Selection -->
                <component
                    :is="currentComponent"
                    :agent="agent"
                    :file-type="selectedContentType"
                    v-if="
                        selectedContentType === 'pdf' ||
                        selectedContentType === 'word' ||
                        selectedContentType === 'markdown' ||
                        selectedContentType === 'audio' ||
                        selectedContentType === 'video' ||
                        selectedContentType === 'web' ||
                        selectedContentType === 'text' ||
                        selectedContentType === 'youtube' ||
                        selectedContentType === 'qa'
                    "
                />

                <!-- Placeholder for other content types -->
                <Card v-else class="h-full min-h-[400px]">
                    <CardContent class="flex h-full items-center justify-center p-8">
                        <div class="text-center">
                            <i
                                :class="`pi ${contentTypes.find((t) => t.id === selectedContentType)?.icon} text-muted-foreground mx-auto mb-4 text-6xl`"
                            />
                            <h3 class="mb-2 text-lg font-medium">
                                {{
                                    t('agents.sources.overview.coming_soon', {
                                        type: t(contentTypes.find((type) => type.id === selectedContentType)?.label ?? ''),
                                    })
                                }}
                            </h3>
                            <p class="text-muted-foreground max-w-md">
                                {{ t(contentTypes.find((type) => type.id === selectedContentType)?.description ?? '') }}
                                {{ t('agents.sources.overview.under_development') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
