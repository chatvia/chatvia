<script lang="ts" setup>
import { Card, CardContent } from '@/components/ui/card';
import AgentLayout from '@/layouts/AgentLayout.vue';
import axios from 'axios';
import { FileText, LoaderCircle } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
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
                    <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="flex items-center justify-center py-12">
                    <Card class="max-w-md">
                        <CardContent class="pt-6">
                            <div class="space-y-2 text-center">
                                <p class="text-destructive font-medium">{{ error }}</p>
                                <button @click="fetchSources" class="text-muted-foreground hover:text-foreground text-sm underline">Try again</button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sources Content -->
                <div v-else>
                    <Card>
                        <CardContent class="pt-6">
                            <div v-if="!sources.length" class="text-muted-foreground py-12 text-center">
                                <FileText class="mx-auto mb-4 h-12 w-12 opacity-50" />
                                <p>No knowledge sources accessed yet</p>
                            </div>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="(item, index) in sources"
                                    :key="index"
                                    class="group bg-card hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                                >
                                    <div class="flex items-start gap-4">
                                        <!-- Rank Badge -->
                                        <div
                                            class="bg-muted text-foreground flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                        >
                                            {{ index + 1 }}
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <!-- File Name and Metadata -->
                                            <div>
                                                <p class="truncate text-base font-semibold">{{ item.name }}</p>
                                                <div class="mt-1.5 flex flex-wrap items-center gap-3">
                                                    <span class="text-foreground text-sm font-medium">
                                                        {{ item.accessed }} access{{ item.accessed !== 1 ? 'es' : '' }}
                                                    </span>
                                                    <span class="text-muted-foreground text-xs" v-if="item.mime_type"> • {{ item.mime_type }} </span>
                                                    <span class="text-muted-foreground text-xs" v-if="item.last_retrieved_at">
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
