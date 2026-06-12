<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { AgentFunction } from '@/types/agent-function';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, Code, FunctionSquare, LoaderCircle, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}

const props = defineProps<Props>();

const { t } = useI18n();

interface FunctionRow extends AgentFunction {
    is_attached: boolean;
}

const loading = ref(true);
const functions = ref<FunctionRow[]>([]);
const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return functions.value;
    return functions.value.filter(
        (f) =>
            f.internal_name.toLowerCase().includes(q) ||
            (f.display_name ?? '').toLowerCase().includes(q) ||
            f.description.toLowerCase().includes(q) ||
            f.webhook_url.toLowerCase().includes(q),
    );
});

async function fetchFunctions(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get(route('agents.sources.functions.data', props.agent.external_id));
        functions.value = response.data.data;
    } catch (err) {
        console.error(err);
        toast.error(t('agents.sources.functions.load_failed'));
    } finally {
        loading.value = false;
    }
}

async function toggle(fn: FunctionRow): Promise<void> {
    if (fn.is_attached) {
        await detach(fn);
    } else {
        await attach(fn);
    }
}

async function attach(fn: FunctionRow): Promise<void> {
    try {
        await axios.post(route('agents.sources.functions.attach', { agent: props.agent.external_id, function: fn.external_id }));
        const i = functions.value.findIndex((f) => f.external_id === fn.external_id);
        if (i !== -1) functions.value[i] = { ...functions.value[i], is_attached: true };
    } catch (err) {
        console.error(err);
        toast.error(t('agents.sources.functions.attach_failed'));
    }
}

async function detach(fn: FunctionRow): Promise<void> {
    try {
        await axios.delete(route('agents.sources.functions.detach', { agent: props.agent.external_id, function: fn.external_id }));
        const i = functions.value.findIndex((f) => f.external_id === fn.external_id);
        if (i !== -1) functions.value[i] = { ...functions.value[i], is_attached: false };
    } catch (err) {
        console.error(err);
        toast.error(t('agents.sources.functions.detach_failed'));
    }
}

onMounted(fetchFunctions);
</script>

<template>
    <AgentLayout :agent="agent">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl space-y-6">
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Code class="h-5 w-5" />
                                    {{ t('agents.sources.functions.title') }}
                                </CardTitle>
                                <CardDescription>
                                    {{ t('agents.sources.functions.description') }}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="relative max-w-sm">
                            <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input v-model="search" :placeholder="t('agents.sources.functions.search_placeholder')" class="ps-9" />
                        </div>

                        <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                            {{ t('agents.sources.functions.loading') }}
                        </div>

                        <div v-else-if="functions.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                            <FunctionSquare class="mx-auto mb-3 h-10 w-10 opacity-40" />
                            <p class="font-medium">{{ t('agents.sources.functions.empty_title') }}</p>
                            <p class="mt-1">
                                {{ t('agents.sources.functions.empty_prefix') }}
                                <Link :href="route('integrations.functions.edit')" class="underline">{{ t('agents.sources.functions.settings_link') }}</Link>.
                            </p>
                        </div>

                        <div v-else-if="filtered.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                            {{ t('agents.sources.functions.no_match') }}
                        </div>

                        <div v-else class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="h-11 ps-5">{{ t('agents.sources.functions.column_function') }}</TableHead>
                                        <TableHead class="h-11 w-40">{{ t('agents.sources.functions.column_webhook') }}</TableHead>
                                        <TableHead class="h-11 w-32 pe-5">{{ t('agents.sources.functions.column_enabled') }}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="fn in filtered" :key="fn.external_id">
                                        <TableCell class="py-4 ps-5">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span v-if="fn.display_name" class="text-sm font-medium">{{ fn.display_name }}</span>
                                                    <code class="bg-muted rounded px-1.5 py-0.5 text-xs font-semibold">{{ fn.internal_name }}</code>
                                                    <Badge v-if="!fn.is_enabled" variant="secondary">{{ t('agents.sources.functions.disabled') }}</Badge>
                                                    <Badge v-if="fn.last_error" variant="destructive" class="gap-1">
                                                        <AlertCircle class="h-3 w-3" />
                                                        {{ t('agents.sources.functions.last_call_failed') }}
                                                    </Badge>
                                                </div>
                                                <p class="text-muted-foreground mt-1 line-clamp-1 text-sm">{{ fn.description }}</p>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-muted-foreground py-4 text-xs">
                                            {{ fn.webhook_method }} {{ fn.webhook_url }}
                                        </TableCell>
                                        <TableCell class="py-4 pe-5">
                                            <Switch
                                                :model-value="fn.is_attached"
                                                :disabled="!fn.is_enabled"
                                                :title="!fn.is_enabled ? t('agents.sources.functions.enable_first') : undefined"
                                                @update:model-value="toggle(fn)"
                                            />
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
