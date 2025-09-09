<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import Files from './Files.vue';
import WebComponent from './WebComponent.vue';
import TextComponent from './TextComponent.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Globe,
    Headphones,
    Video,
    Youtube,
    Type,
    Database,
    FileImage,
    FileCheck
} from 'lucide-vue-next';

interface Props {
    agent: any;
    currentType?: string;
}

const props = defineProps<Props>();
const page = usePage();

// Knowledge base content types
const contentTypes = [
    {
        id: 'pdf',
        label: 'PDF',
        icon: FileImage,
        description: 'Upload and manage PDF documents',
        route: 'agents.sources.pdf'
    },
    {
        id: 'word',
        label: 'Word',
        icon: FileCheck,
        description: 'Upload and manage Word documents',
        route: 'agents.sources.word'
    },
    {
        id: 'text',
        label: 'Text',
        icon: Type,
        description: 'Add custom text content and notes directly',
        route: 'agents.sources.text'
    },
    {
        id: 'web',
        label: 'Website',
        icon: Globe,
        description: 'Crawl websites and web pages for knowledge',
        route: 'agents.sources.web'
    },
    {
        id: 'audio',
        label: 'Audio',
        icon: Headphones,
        description: 'Upload audio files and podcasts for transcription',
        route: null // Will be implemented later
    },
    {
        id: 'video',
        label: 'Video',
        icon: Video,
        description: 'Upload video files for transcription and analysis',
        route: null // Will be implemented later
    },
    {
        id: 'youtube',
        label: 'YouTube',
        icon: Youtube,
        description: 'Import YouTube videos and extract transcripts',
        route: null // Will be implemented later
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
    return 'pdf'; // default to PDF
};

// Current selected content type
const selectedContentType = ref(getCurrentType());

// Computed property for current content component
const currentComponent = computed(() => {
    switch (selectedContentType.value) {
        case 'pdf':
        case 'word':
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

// Update selected type when component mounts or route changes
onMounted(() => {
    selectedContentType.value = getCurrentType();
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
                            <Database class="h-4 w-4" />
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
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary ring-offset-2'
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
                                        <component :is="contentType.icon" class="h-6 w-6" />
                                    </div>
                                    <h3 class="font-medium text-sm">{{ contentType.label }}</h3>
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
                    v-if="selectedContentType === 'pdf' || selectedContentType === 'word' || selectedContentType === 'web' || selectedContentType === 'text'"
                />

                <!-- Placeholder for other content types -->
                <Card
                    v-else
                    class="h-full min-h-[400px]"
                >
                    <CardContent class="flex items-center justify-center h-full p-8">
                        <div class="text-center">
                            <component
                                :is="contentTypes.find(t => t.id === selectedContentType)?.icon"
                                class="h-16 w-16 mx-auto mb-4 text-muted-foreground"
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
