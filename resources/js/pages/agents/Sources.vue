<script lang="ts" setup>
/**
 * Source-usage analytics: which knowledge sources, functions and MCP tools the
 * agent used, how often and when last, in three tabs.
 */
import { errorMessage } from '@/api/http';
import { fetchSourceUsage, type FunctionUsageItem, type KnowledgeUsageItem, type McpToolUsageItem } from '@/api/sourceUsage';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Database, FileText, Globe, LoaderCircle, Plug, Type, Wrench } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

type KnowledgeItem = KnowledgeUsageItem;
type FunctionItem = FunctionUsageItem;
type McpToolItem = McpToolUsageItem;

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
        const usage = await fetchSourceUsage(props.agent.external_id);
        knowledge.value = usage.knowledge ?? [];
        functions.value = usage.functions ?? [];
        mcpTools.value = usage.mcp_tools ?? [];
    } catch (err) {
        error.value = errorMessage(err, t('agents.usage.load_error'));
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
    if (sec < 60) return t('agents.usage.relative.just_now');
    if (min < 60) return t('agents.usage.relative.minutes_ago', { count: min }, min);
    if (hour < 24) return t('agents.usage.relative.hours_ago', { count: hour }, hour);
    if (day < 7) return t('agents.usage.relative.days_ago', { count: day }, day);
    if (day < 30) {
        const weeks = Math.floor(day / 7);
        return t('agents.usage.relative.weeks_ago', { count: weeks }, weeks);
    }
    if (day < 365) {
        const months = Math.floor(day / 30);
        return t('agents.usage.relative.months_ago', { count: months }, months);
    }
    const years = Math.floor(day / 365);
    return t('agents.usage.relative.years_ago', { count: years }, years);
};

const subKindLabel = (subKind: string): string => {
    const key = `agents.usage.sub_kind.${subKind}`;
    const translated = t(key);
    return translated === key ? subKind : translated;
};

const subKindIcon = (subKind: string) => {
    return (
        {
            source_file: FileText,
            source_website: Globe,
            source_text: Type,
            agent_website_url: Globe,
        }[subKind] ?? FileText
    );
};

onMounted(() => {
    fetchData();
});
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.usage.title')" :description="t('agents.usage.description')">
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
                                <button @click="fetchData" class="text-muted-foreground hover:text-foreground text-sm underline">
                                    {{ t('agents.usage.try_again') }}
                                </button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div v-else>
                    <Tabs v-model="activeTab" class="mb-4">
                        <TabsList>
                            <TabsTrigger value="knowledge">
                                <Database class="me-1.5 h-3.5 w-3.5" />
                                {{ t('agents.usage.tabs.knowledge') }}
                                <Badge variant="secondary" class="ms-2">{{ totals.knowledge }}</Badge>
                            </TabsTrigger>
                            <TabsTrigger value="functions">
                                <Wrench class="me-1.5 h-3.5 w-3.5" />
                                {{ t('agents.usage.tabs.functions') }}
                                <Badge variant="secondary" class="ms-2">{{ totals.functions }}</Badge>
                            </TabsTrigger>
                            <TabsTrigger value="mcp">
                                <Plug class="me-1.5 h-3.5 w-3.5" />
                                {{ t('agents.usage.tabs.mcp') }}
                                <Badge variant="secondary" class="ms-2">{{ totals.mcp }}</Badge>
                            </TabsTrigger>
                        </TabsList>
                    </Tabs>

                    <Card>
                        <CardContent class="pt-6">
                            <!-- Knowledge tab -->
                            <div v-if="activeTab === 'knowledge'">
                                <div v-if="!knowledge.length" class="text-muted-foreground py-12 text-center">
                                    <Database class="mx-auto mb-4 h-12 w-12 opacity-50" />
                                    <p>{{ t('agents.usage.knowledge_empty') }}</p>
                                    <p class="mt-1 text-xs">
                                        {{ t('agents.usage.knowledge_empty_hint') }}
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
                                                        {{ t('agents.usage.retrievals', { count: item.count }, item.count) }}
                                                    </span>
                                                    <span v-if="item.mime_type" class="text-muted-foreground text-xs">• {{ item.mime_type }}</span>
                                                    <span v-if="item.last_used_at" class="text-muted-foreground text-xs"
                                                        >• {{ t('agents.usage.last_used', { time: formatRelative(item.last_used_at) }) }}</span
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
                                    <p>{{ t('agents.usage.functions_empty') }}</p>
                                    <p class="mt-1 text-xs">{{ t('agents.usage.functions_empty_hint') }}</p>
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
                                                        {{ t('agents.usage.calls', { count: item.count }, item.count) }}
                                                    </span>
                                                    <span v-if="item.last_used_at" class="text-muted-foreground text-xs"
                                                        >• {{ t('agents.usage.last_called', { time: formatRelative(item.last_used_at) }) }}</span
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
                                    <p>{{ t('agents.usage.mcp_empty') }}</p>
                                    <p class="mt-1 text-xs">{{ t('agents.usage.mcp_empty_hint') }}</p>
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
                                                <p class="text-muted-foreground mt-0.5 text-sm">
                                                    {{ t('agents.usage.via_server', { name: item.server_name }) }}
                                                </p>
                                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                    <Badge v-if="item.connector_slug" variant="secondary">{{ item.connector_slug }}</Badge>
                                                    <span class="text-foreground text-sm font-medium">
                                                        {{ t('agents.usage.calls', { count: item.count }, item.count) }}
                                                    </span>
                                                    <span v-if="item.last_used_at" class="text-muted-foreground text-xs"
                                                        >• {{ t('agents.usage.last_called', { time: formatRelative(item.last_used_at) }) }}</span
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
