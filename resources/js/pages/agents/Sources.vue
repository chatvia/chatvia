<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';
import { ref, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FileText, LoaderCircle } from 'lucide-vue-next';
import axios from 'axios';

interface Props {
    agent: any
}

const props = defineProps<Props>();

const loading = ref(true);
const sources = ref<any[]>([]);
const error = ref<string | null>(null);

const fetchSources = async () => {
    loading.value = true;
    error.value = null;
    
    try {
        const response = await axios.get(route('agents.analytics.sources.data', props.agent.id));
        sources.value = response.data;
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load sources';
        console.error('Error fetching sources:', err);
    } finally {
        loading.value = false;
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
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <FileText class="h-5 w-5" />
                        Most Accessed Sources
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="!sources.length" class="text-center py-12 text-muted-foreground">
                        <FileText class="h-12 w-12 mx-auto mb-4 opacity-50" />
                        <p>No knowledge sources accessed yet</p>
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="(item, index) in sources" :key="index" class="flex items-start gap-4 p-4 rounded-lg border bg-muted/30 hover:bg-muted/50 transition-colors">
                            <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 text-sm font-bold text-primary">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate">{{ item.name }}</p>
                                <div class="flex items-center gap-4 mt-1">
                                    <p class="text-sm text-muted-foreground">
                                        <span class="font-semibold text-foreground">{{ item.accessed }}</span> accesses
                                    </p>
                                    <p class="text-xs text-muted-foreground" v-if="item.mime_type">
                                        {{ item.mime_type }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AgentLayout>
</template>

