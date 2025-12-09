<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, onMounted } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { FileText, LoaderCircle } from 'lucide-vue-next';
import axios from 'axios';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData
}

const props = defineProps<Props>();

const loading = ref(true);
const sources = ref<any[]>([]);
const error = ref<string | null>(null);

const fetchSources = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await axios.get(route('agents.analytics.sources.data', props.agent.external_id));
        sources.value = response.data;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load sources';
        console.error('Error fetching sources:', err);
    } finally {
        loading.value = false;
    }
};

// Format date to relative time
const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSeconds = Math.floor(diffMs / 1000);
    const diffMinutes = Math.floor(diffSeconds / 60);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffSeconds < 60) {
        return 'just now';
    } else if (diffMinutes < 60) {
        return `${diffMinutes} minute${diffMinutes !== 1 ? 's' : ''} ago`;
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
    } else if (diffDays < 7) {
        return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
    } else if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        return `${weeks} week${weeks !== 1 ? 's' : ''} ago`;
    } else if (diffDays < 365) {
        const months = Math.floor(diffDays / 30);
        return `${months} month${months !== 1 ? 's' : ''} ago`;
    } else {
        const years = Math.floor(diffDays / 365);
        return `${years} year${years !== 1 ? 's' : ''} ago`;
    }
};

onMounted(() => {
    fetchSources();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Most Accessed Sources" description="View the most frequently accessed knowledge sources">
        <template #header-icon>
            <FileText class="h-5 w-5" />
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-6xl">
                <!-- Loading State -->
                <div v-if="loading" class="flex items-center justify-center py-12">
                    <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="flex items-center justify-center py-12">
                    <Card class="max-w-md">
                        <CardContent class="pt-6">
                            <div class="text-center space-y-2">
                                <p class="text-destructive font-medium">{{ error }}</p>
                                <button
                                    @click="fetchSources"
                                    class="text-sm text-muted-foreground hover:text-foreground underline"
                                >
                                    Try again
                                </button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sources Content -->
                <div v-else>
                    <Card>
                        <CardContent class="pt-6">
                            <div v-if="!sources.length" class="text-center py-12 text-muted-foreground">
                                <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>No knowledge sources accessed yet</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div 
                                    v-for="(item, index) in sources" 
                                    :key="index" 
                                    class="group p-4 rounded-lg border bg-card hover:bg-accent/50 transition-colors"
                                >
                                    <div class="flex items-start gap-4">
                                        <!-- Rank Badge -->
                                        <div 
                                            class="h-10 w-10 rounded-full bg-muted flex items-center justify-center flex-shrink-0 text-sm font-bold text-foreground"
                                        >
                                            {{ index + 1 }}
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <!-- File Name and Metadata -->
                                            <div>
                                                <p class="font-semibold text-base truncate">{{ item.name }}</p>
                                                <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                                    <span class="text-sm font-medium text-foreground">
                                                        {{ item.accessed }} access{{ item.accessed !== 1 ? 'es' : '' }}
                                                    </span>
                                                    <span class="text-xs text-muted-foreground" v-if="item.mime_type">
                                                        • {{ item.mime_type }}
                                                    </span>
                                                    <span class="text-xs text-muted-foreground" v-if="item.last_retrieved_at">
                                                        • Last accessed {{ formatDate(item.last_retrieved_at) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>

