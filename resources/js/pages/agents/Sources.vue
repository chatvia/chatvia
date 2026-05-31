<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AgentLayout from '@/layouts/AgentLayout.vue';
import axios from 'axios';
import { Activity, Database, FileText, Globe, LoaderCircle, Plug, Type, Wrench, Youtube } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

const props = defineProps<Props>();

interface KnowledgeItem {
    name: string;
    sub_kind: string;
    mime_type: string | null;
    count: number;
    last_used_at: string | null;
}

interface FunctionItem {
    name: string;
    description: string;
    count: number;
    last_used_at: string | null;
}

interface McpToolItem {
    tool_name: string;
    tool_description: string | null;
    server_name: string;
    server_slug: string;
    connector_slug: string | null;
    count: number;
    last_used_at: string | null;
}

const loading = ref(true);
const error = ref<string | null>(null);
const knowledge = ref<KnowledgeItem[]>([]);
const functions = ref<FunctionItem[]>([]);
const mcpTools = ref<McpToolItem[]>([]);
const activeTab = ref<'knowledge' | 'functions' | 'mcp'>('knowledge');

const totals = computed(() => ({
    knowledge: knowledge.value.length,
    functions: functions.value.length,
    mcp: mcpTools.value.length,
}));

const fetchData = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('agents.analytics.sources.data', props.agent.external_id));
        knowledge.value = response.data.knowledge ?? [];
        functions.value = response.data.functions ?? [];
        mcpTools.value = response.data.mcp_tools ?? [];
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to load usage';
        console.error('Error fetching sources analytics:', err);
    } finally {
        loading.value = false;
    }
};

const formatRelative = (value: string | null): string => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    const diff = Date.now() - date.getTime();
    const sec = Math.floor(diff / 1000);
    const min = Math.floor(sec / 60);
    const hour = Math.floor(min / 60);
    const day = Math.floor(hour / 24);
    if (sec < 60) return 'just now';
    if (min < 60) return `${min} minute${min !== 1 ? 's' : ''} ago`;
    if (hour < 24) return `${hour} hour${hour !== 1 ? 's' : ''} ago`;
    if (day < 7) return `${day} day${day !== 1 ? 's' : ''} ago`;
    if (day < 30) return `${Math.floor(day / 7)} week${Math.floor(day / 7) !== 1 ? 's' : ''} ago`;
    if (day < 365) return `${Math.floor(day / 30)} month${Math.floor(day / 30) !== 1 ? 's' : ''} ago`;
    return `${Math.floor(day / 365)} year${Math.floor(day / 365) !== 1 ? 's' : ''} ago`;
};

const subKindLabel = (subKind: string): string => {
    return (
        {
            source_file: 'File',
            source_website: 'Website',
            source_text: 'Text',
            source_youtube_video: 'YouTube',
            agent_website_url: 'Web page',
        }[subKind] ?? subKind
    );
};

const subKindIcon = (subKind: string) => {
    return (
        {
            source_file: FileText,
            source_website: Globe,
            source_text: Type,
            source_youtube_video: Youtube,
            agent_website_url: Globe,
        }[subKind] ?? FileText
    );
};

onMounted(() => {
    fetchData();
});
</script>

<template>
    <AgentLayout :agent="agent" title="Usage Insights" description="See which sources, functions, and MCP servers this agent actually uses">
        <template #header-icon>
            <Activity class="h-5 w-5" />
        </template>

        <div class="flex justify-center">
            <div class="w-full max-w-6xl">
                <div v-if="loading" class="flex items-center justify-center py-12">
                    <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                </div>

                <div v-else-if="error" class="flex items-center justify-center py-12">
                    <Card class="max-w-md">
                        <CardContent class="pt-6">
                            <div class="space-y-2 text-center">
                                <p class="text-destructive font-medium">{{ error }}</p>
                                <button @click="fetchData" class="text-muted-foreground hover:text-foreground text-sm underline">Try again</button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-else>
                    <Tabs v-model="activeTab" class="mb-4">
                        <TabsList>
                            <TabsTrigger value="knowledge">
                                <Database class="mr-1.5 h-3.5 w-3.5" />
                                Knowledge
                                <Badge variant="secondary" class="ml-2">{{ totals.knowledge }}</Badge>
                            </TabsTrigger>
                            <TabsTrigger value="functions">
                                <Wrench class="mr-1.5 h-3.5 w-3.5" />
                                Functions
                                <Badge variant="secondary" class="ml-2">{{ totals.functions }}</Badge>
                            </TabsTrigger>
                            <TabsTrigger value="mcp">
                                <Plug class="mr-1.5 h-3.5 w-3.5" />
                                MCP
                                <Badge variant="secondary" class="ml-2">{{ totals.mcp }}</Badge>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <Card>
                        <CardContent class="pt-6">
                            <!-- Knowledge tab -->
                            <div v-if="activeTab === 'knowledge'">
                                <div v-if="!knowledge.length" class="text-muted-foreground py-12 text-center">
                                    <Database class="mx-auto mb-4 h-12 w-12 opacity-50" />
                                    <p>No knowledge sources retrieved yet</p>
                                    <p class="mt-1 text-xs">
                                        Files, websites, text snippets, and YouTube videos appear here once the agent uses them.
                                    </p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="(item, index) in knowledge"
                                        :key="`k-${index}`"
                                        class="group bg-card hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                                    >
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="bg-muted text-foreground flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                            >
                                                {{ index + 1 }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start gap-2">
                                                    <component
                                                        :is="subKindIcon(item.sub_kind)"
                                                        class="text-muted-foreground mt-0.5 h-4 w-4 flex-shrink-0"
                                                    />
                                                    <p class="truncate text-base font-semibold">{{ item.name }}</p>
                                                </div>
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline">{{ subKindLabel(item.sub_kind) }}</Badge>
                                                    <span class="text-foreground text-sm font-medium">
                                                        {{ item.count }} retrieval{{ item.count !== 1 ? 's' : '' }}
                                                    </span>
                                                    <span v-if="item.mime_type" class="text-muted-foreground text-xs">• {{ item.mime_type }}</span>
                                                    <span v-if="item.last_used_at" class="text-muted-foreground text-xs"
                                                        >• Last used {{ formatRelative(item.last_used_at) }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Functions tab -->
                            <div v-else-if="activeTab === 'functions'">
                                <div v-if="!functions.length" class="text-muted-foreground py-12 text-center">
                                    <Wrench class="mx-auto mb-4 h-12 w-12 opacity-50" />
                                    <p>No function calls yet</p>
                                    <p class="mt-1 text-xs">Webhook functions appear here once the agent invokes them.</p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="(item, index) in functions"
                                        :key="`f-${index}`"
                                        class="group bg-card hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                                    >
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="bg-muted text-foreground flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                            >
                                                {{ index + 1 }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start gap-2">
                                                    <Wrench class="text-muted-foreground mt-0.5 h-4 w-4 flex-shrink-0" />
                                                    <p class="truncate font-mono text-base font-semibold">{{ item.name }}</p>
                                                </div>
                                                <p v-if="item.description" class="text-muted-foreground mt-1 line-clamp-2 text-sm">
                                                    {{ item.description }}
                                                </p>
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                    <span class="text-foreground text-sm font-medium">
                                                        {{ item.count }} call{{ item.count !== 1 ? 's' : '' }}
                                                    </span>
                                                    <span v-if="item.last_used_at" class="text-muted-foreground text-xs"
                                                        >• Last called {{ formatRelative(item.last_used_at) }}</span
                                                    >
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- MCP tab -->
                            <div v-else>
                                <div v-if="!mcpTools.length" class="text-muted-foreground py-12 text-center">
                                    <Plug class="mx-auto mb-4 h-12 w-12 opacity-50" />
                                    <p>No MCP tool calls yet</p>
                                    <p class="mt-1 text-xs">Individual tools from connected MCP servers appear here once the agent invokes them.</p>
                                </div>
                                <div v-else class="space-y-3">
                                    <div
                                        v-for="(item, index) in mcpTools"
                                        :key="`m-${index}`"
                                        class="group bg-card hover:bg-accent/50 rounded-lg border p-4 transition-colors"
                                    >
                                        <div class="flex items-start gap-4">
                                            <div
                                                class="bg-muted text-foreground flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                            >
                                                {{ index + 1 }}
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-start gap-2">
                                                    <Plug class="text-muted-foreground mt-0.5 h-4 w-4 flex-shrink-0" />
                                                    <p class="truncate font-mono text-base font-semibold">{{ item.tool_name }}</p>
                                                </div>
                                                <p class="text-muted-foreground mt-0.5 text-sm">via {{ item.server_name }}</p>
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                    <Badge v-if="item.connector_slug" variant="secondary">{{ item.connector_slug }}</Badge>
                                                    <span class="text-foreground text-sm font-medium">
                                                        {{ item.count }} call{{ item.count !== 1 ? 's' : '' }}
                                                    </span>
                                                    <span v-if="item.last_used_at" class="text-muted-foreground text-xs"
                                                        >• Last called {{ formatRelative(item.last_used_at) }}</span
                                                    >
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
