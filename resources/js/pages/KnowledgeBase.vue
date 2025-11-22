<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Database,
    Search,
    Download,
    Play,
    FileText,
    Bot,
    Loader2,
    File
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import axios from 'axios';

// Types
interface Agent {
    id: number;
    name: string;
    sub_domain: string;
    url: string;
    chat_url: string;
}

interface KnowledgeFile {
    id: number;
    agent: Agent;
    filename: string;
    url: string;
    is_audio: boolean;
    size_in_kb: number;
    mime_type: string;
    length: number;
    status: string;
    retrieval_count: number;
    created_at: string;
    updated_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Knowledge Base',
        href: '/knowledge-base',
    },
];

// State
const knowledgeFiles = ref<KnowledgeFile[]>([]);
const isLoading = ref(true);
const searchQuery = ref('');

// Computed
const filteredFiles = computed(() => {
    if (!searchQuery.value) return knowledgeFiles.value;

    const query = searchQuery.value.toLowerCase();
    return knowledgeFiles.value.filter(file =>
        file.filename.toLowerCase().includes(query) ||
        file.agent.name.toLowerCase().includes(query) ||
        file.agent.sub_domain.toLowerCase().includes(query)
    );
});


// Methods
const fetchKnowledgeFiles = async () => {
    try {
        const response = await axios.get(route('knowledge-base.list'));
        knowledgeFiles.value = response.data;
    } catch (error) {
        console.error('Failed to fetch knowledge files:', error);
    } finally {
        isLoading.value = false;
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatFileSize = (sizeInKb: number) => {
    if (sizeInKb < 1024) {
        return `${sizeInKb.toFixed(1)} KB`;
    }
    return `${(sizeInKb / 1024).toFixed(1)} MB`;
};

const getFileIcon = (mimeType: string) => {
    if (mimeType.includes('pdf') || mimeType.includes('word')) return FileText;
    return File;
};

const truncateFilename = (filename: string, maxLength: number = 20) => {
    if (filename.length <= maxLength) return filename;

    const ext = filename.split('.').pop() || '';
    const nameWithoutExt = filename.substring(0, filename.lastIndexOf('.'));

    if (nameWithoutExt.length > maxLength - ext.length - 3) {
        return nameWithoutExt.substring(0, maxLength - ext.length - 3) + '...' + '.' + ext;
    }

    return filename;
};

const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
        case 'uploaded':
            return { variant: 'secondary' as const, text: 'Uploaded', color: 'text-blue-600' };
        case 'ingested':
            return { variant: 'default' as const, text: 'Completed', color: 'text-green-600' };
        case 'ingesting':
            return { variant: 'outline' as const, text: 'Processing', color: 'text-yellow-600' };
        case 'failed':
            return { variant: 'destructive' as const, text: 'Failed', color: 'text-red-600' };
        default:
            return { variant: 'outline' as const, text: status, color: 'text-gray-600' };
    }
};

const handleDownload = (file: KnowledgeFile) => {
    // Create a temporary link element to trigger download
    const link = document.createElement('a');
    link.href = file.url;
    link.download = file.filename;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};



// Lifecycle
onMounted(() => {
    fetchKnowledgeFiles();
});
</script>

<template>
    <Head title="Knowledge Base" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto max-w-5xl py-8">
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Knowledge Base</h1>
                    <p class="mt-1 text-muted-foreground">
                        View and manage all knowledge files across your agents
                    </p>
                </div>
            </div>

            <!-- Search and filters -->
            <div class="mb-6 flex items-center">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        class="pl-10"
                        placeholder="Search files or agents..."
                    />
                </div>
            </div>

            <!-- Loading state -->
            <div v-if="isLoading" class="flex h-64 items-center justify-center">
                <Loader2 class="h-8 w-8 animate-spin text-primary" />
                <span class="ml-2 text-lg">Loading knowledge files...</span>
            </div>

            <!-- Empty state -->
            <Card v-else-if="knowledgeFiles.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Database class="h-12 w-12 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No knowledge files yet</h3>
                <p class="mt-2 text-center text-muted-foreground">
                    Start by uploading files to your agents
                </p>
                <Button as-child size="lg" class="mt-6">
                    <Link :href="route('agents.index')" class="flex items-center gap-2">
                        <Bot class="h-4 w-4" />
                        View Agents
                    </Link>
                </Button>
            </Card>

            <!-- No search results -->
            <Card v-else-if="filteredFiles.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Search class="h-12 w-12 text-muted-foreground opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No matching files</h3>
                <p class="mt-2 text-center text-muted-foreground">
                    No files found matching "{{ searchQuery }}"
                </p>
                <Button variant="outline" class="mt-6" @click="searchQuery = ''">
                    Clear Search
                </Button>
            </Card>

                    <!-- Files Grid -->
                    <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="file in filteredFiles" :key="file.id" class="overflow-hidden">
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-xl">
                                    <component :is="getFileIcon(file.mime_type)" class="h-5 w-5 flex-shrink-0" />
                                    <TooltipProvider>
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                <span class="truncate">{{ truncateFilename(file.filename) }}</span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>{{ file.filename }}</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </TooltipProvider>
                                </CardTitle>
                                <div class="mt-2 mb-1">
                                    <Badge :variant="getStatusBadge(file.status).variant" class="text-xs">
                                        {{ getStatusBadge(file.status).text }}
                                    </Badge>
                                </div>
                                <CardDescription class="flex items-center gap-1">
                                    <Bot class="h-3 w-3" />
                                    <a
                                        :href="file.agent.url"
                                        target="_blank"
                                        class="text-primary"
                                    >
                                        {{ file.agent.name }}
                                </a>
                                    <a :href="file.agent.url" target="_blank" class="text-primary">• {{ file.agent.sub_domain }}.chatvia.ai</a>
                                </CardDescription>
                            </CardHeader>

                            <CardContent>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="flex flex-col">
                                        <span class="text-muted-foreground">Size</span>
                                        <span class="text-lg font-medium">{{ formatFileSize(file.size_in_kb) }}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-muted-foreground">
                                            {{ file.is_audio ? 'Duration' : 'Pages' }}
                                        </span>
                                        <span class="text-lg font-medium">
                                            <span v-if="file.is_audio">{{ file.length }}m</span>
                                            <span v-else>{{ file.length }}</span>
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-muted-foreground">Retrievals</span>
                                        <span class="text-lg font-medium">{{ file.retrieval_count }}</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-muted-foreground">Created</span>
                                        <span>{{ formatDate(file.created_at) }}</span>
                                    </div>
                                </div>
                            </CardContent>

                            <CardFooter class="flex justify-center gap-3 border-t p-4">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="handleDownload(file)"
                                    class="gap-1 flex-1"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    Download
                                </Button>
                                <Button
                                    v-if="file.status === 'uploaded'"
                                    size="sm"
                                    as-child
                                    class="gap-1 flex-1"
                                >
                                    <Link :href="route('agents.sources', { agent: file.agent.id })">
                                        <Play class="h-3.5 w-3.5" />
                                        Train
                                    </Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
        </div>
    </AppLayout>
</template>


