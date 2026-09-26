<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConnectorText } from '@/composables/useConnectorText';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { AgentMcpServer, Connector } from '@/types/connector';
import axios from 'axios';
import { LoaderCircle, Plug, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    agent: { external_id: string; name: string };
}

const props = defineProps<Props>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

// Featured row state from the backend:
//   - attached   — account-level row exists AND attached to this agent
//   - unattached — account-level row exists, not attached to this agent
//   - available  — no account-level row yet (connect it in Settings)
type FeaturedState = 'attached' | 'unattached' | 'available';

interface FeaturedRow {
    connector: Connector;
    state: FeaturedState;
    server: AgentMcpServer | null;
    always_available: boolean;
}

interface CustomRow {
    external_id: string;
    name: string;
    slug: string;
    url: string;
    is_enabled: boolean;
    catalog_tools_count: number;
    is_attached: boolean;
    always_available: boolean;
}

const loading = ref(true);
const featured = ref<FeaturedRow[]>([]);
const customConnectors = ref<CustomRow[]>([]);
const search = ref('');

function logoUrl(c: Connector): string {
    return c.logoUrl ?? `https://cdn.simpleicons.org/${c.icon}/${c.brandColor}`;
}

function matches(row: { name: string; description?: string | null; url?: string }): boolean {
    const q = search.value.trim().toLowerCase();
    if (!q) return true;
    return row.name.toLowerCase().includes(q) || (row.description ?? '').toLowerCase().includes(q) || (row.url ?? '').toLowerCase().includes(q);
}

// Backend returns rows pre-sorted by ConnectorData.priority. We re-sort here
// only to float attached ("enabled") rows above the rest while preserving the
// priority order within each group — `sort` is stable in modern JS engines.
const filteredFeatured = computed(() =>
    featured.value
        .filter((f) => matches({ name: connectorName(f.connector), description: connectorDescription(f.connector) }))
        .slice()
        .sort((a, b) => Number(b.state === 'attached') - Number(a.state === 'attached')),
);
const filteredCustom = computed(() => customConnectors.value.filter(matches));

async function fetchAll(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get(route('agents.sources.connectors.data', props.agent.external_id));
        featured.value = response.data.featured;
        customConnectors.value = response.data.custom;
    } catch (err) {
        console.error(err);
        toast.error(t('agents.sources.connectors.load_failed'));
    } finally {
        loading.value = false;
    }
}

// Toggle = attach/detach. Greyed-out toggles (state === 'available') don't
// fire — the Switch is disabled. The pivot row's existence IS the enabled
// state; there's no separate flag.
async function toggleFeatured(row: FeaturedRow): Promise<void> {
    if (row.state === 'available' || !row.server) return;

    if (row.state === 'attached') {
        await detach(row.server.slug, () => {
            const i = featured.value.findIndex((f) => f.connector.slug === row.connector.slug);
            if (i !== -1) featured.value[i] = { ...featured.value[i], state: 'unattached', always_available: false };
        });
    } else {
        await attach(row.server.slug, () => {
            const i = featured.value.findIndex((f) => f.connector.slug === row.connector.slug);
            if (i !== -1) featured.value[i] = { ...featured.value[i], state: 'attached' };
        });
    }
}

async function toggleCustom(row: CustomRow): Promise<void> {
    if (row.is_attached) {
        await detach(row.slug, () => {
            const i = customConnectors.value.findIndex((c) => c.external_id === row.external_id);
            if (i !== -1) customConnectors.value[i] = { ...customConnectors.value[i], is_attached: false, always_available: false };
        });
    } else {
        await attach(row.slug, () => {
            const i = customConnectors.value.findIndex((c) => c.external_id === row.external_id);
            if (i !== -1) customConnectors.value[i] = { ...customConnectors.value[i], is_attached: true };
        });
    }
}

async function attach(slug: string, onSuccess: () => void): Promise<void> {
    try {
        await axios.post(route('agents.sources.connectors.attach', { agent: props.agent.external_id, slug }));
        onSuccess();
    } catch (err) {
        console.error(err);
        toast.error(t('agents.sources.connectors.attach_failed'));
    }
}

async function detach(slug: string, onSuccess: () => void): Promise<void> {
    try {
        await axios.delete(route('agents.sources.connectors.detach', { agent: props.agent.external_id, slug }));
        onSuccess();
    } catch (err) {
        console.error(err);
        toast.error(t('agents.sources.connectors.detach_failed'));
    }
}

// "Always on": the connector's tools ship on every turn, bypassing the
// relevance router. Capped server-side (10 tools per agent) — a breach comes
// back as a 422 whose message we show verbatim.
async function setAlwaysAvailable(slug: string, value: boolean, onSuccess: () => void): Promise<void> {
    try {
        await axios.put(route('agents.sources.connectors.always_available', { agent: props.agent.external_id, slug }), {
            always_available: value,
        });
        onSuccess();
    } catch (err) {
        console.error(err);
        const message = axios.isAxiosError(err) && err.response?.status === 422 ? err.response.data?.message : null;
        toast.error(typeof message === 'string' && message !== '' ? message : t('agents.sources.connectors.always_failed'));
    }
}

async function toggleFeaturedAlways(row: FeaturedRow): Promise<void> {
    if (row.state !== 'attached' || !row.server) return;
    const next = !row.always_available;
    await setAlwaysAvailable(row.server.slug, next, () => {
        const i = featured.value.findIndex((f) => f.connector.slug === row.connector.slug);
        if (i !== -1) featured.value[i] = { ...featured.value[i], always_available: next };
    });
}

async function toggleCustomAlways(row: CustomRow): Promise<void> {
    if (!row.is_attached) return;
    const next = !row.always_available;
    await setAlwaysAvailable(row.slug, next, () => {
        const i = customConnectors.value.findIndex((c) => c.external_id === row.external_id);
        if (i !== -1) customConnectors.value[i] = { ...customConnectors.value[i], always_available: next };
    });
}

onMounted(fetchAll);
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.sources.page_title.connectors')">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl space-y-6">
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Plug class="h-5 w-5" />
                                    {{ t('agents.sources.connectors.title') }}
                                </CardTitle>
                                <CardDescription>
                                    {{ t('agents.sources.connectors.description_prefix') }}
                                    <a :href="route('integrations.connectors.edit')" class="underline">{{
                                        t('agents.sources.connectors.settings_link')
                                    }}</a
                                    >.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="relative max-w-sm">
                            <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input v-model="search" :placeholder="t('agents.sources.connectors.search_placeholder')" class="ps-9" />
                        </div>

                        <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                            {{ t('agents.sources.connectors.loading') }}
                        </div>

                        <div v-else class="space-y-8">
                            <section v-if="filteredCustom.length > 0">
                                <h2 class="mb-3 text-base font-semibold">{{ t('agents.sources.connectors.custom_title') }}</h2>
                                <div class="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="h-11 ps-5">{{ t('agents.sources.connectors.column_connector') }}</TableHead>
                                                <TableHead class="h-11 w-32">{{ t('agents.sources.connectors.column_tools') }}</TableHead>
                                                <TableHead class="h-11 w-32">{{ t('agents.sources.connectors.column_enabled') }}</TableHead>
                                                <TableHead class="h-11 w-36 pe-5" :title="t('agents.sources.connectors.always_hint')">
                                                    {{ t('agents.sources.connectors.column_always') }}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="row in filteredCustom"
                                                :key="row.external_id"
                                                class="cursor-pointer"
                                                @click="toggleCustom(row)"
                                            >
                                                <TableCell class="py-4 ps-5">
                                                    <div class="flex items-center gap-4">
                                                        <div class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                                            <Plug class="text-muted-foreground h-5 w-5" />
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="leading-tight font-medium">{{ row.name }}</p>
                                                            <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ row.url }}</p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="py-4 text-sm">{{ row.catalog_tools_count }}</TableCell>
                                                <TableCell class="py-4" @click.stop>
                                                    <Switch :model-value="row.is_attached" @update:model-value="toggleCustom(row)" />
                                                </TableCell>
                                                <TableCell class="py-4 pe-5" @click.stop>
                                                    <Switch
                                                        :model-value="row.always_available"
                                                        :disabled="!row.is_attached"
                                                        :title="t('agents.sources.connectors.always_hint')"
                                                        @update:model-value="toggleCustomAlways(row)"
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>

                            <section>
                                <div v-if="filteredFeatured.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                                    {{ t('agents.sources.connectors.no_match') }}
                                </div>
                                <div v-else class="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="h-11 ps-5">{{ t('agents.sources.connectors.column_connector') }}</TableHead>
                                                <TableHead class="h-11 w-32">{{ t('agents.sources.connectors.column_tools') }}</TableHead>
                                                <TableHead class="h-11 w-32">{{ t('agents.sources.connectors.column_enabled') }}</TableHead>
                                                <TableHead class="h-11 w-36 pe-5" :title="t('agents.sources.connectors.always_hint')">
                                                    {{ t('agents.sources.connectors.column_always') }}
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="row in filteredFeatured"
                                                :key="row.connector.slug"
                                                :class="{ 'cursor-pointer': !row.connector.comingSoon && row.state !== 'available' }"
                                                @click="toggleFeatured(row)"
                                            >
                                                <TableCell class="py-4 ps-5">
                                                    <div class="flex items-center gap-4">
                                                        <div
                                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                                            :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                                                        >
                                                            <img :src="logoUrl(row.connector)" :alt="row.connector.name" class="h-5 w-5" />
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="leading-tight font-medium">{{ connectorName(row.connector) }}</p>
                                                            <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                                                {{ connectorDescription(row.connector) }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="py-4 text-sm">
                                                    {{ row.server ? row.server.catalog_tools_count : '—' }}
                                                </TableCell>
                                                <TableCell class="py-4" @click.stop>
                                                    <Badge v-if="row.connector.comingSoon" variant="outline">{{
                                                        t('agents.sources.connectors.soon')
                                                    }}</Badge>
                                                    <Switch
                                                        v-else
                                                        :model-value="row.state === 'attached'"
                                                        :disabled="row.state === 'available'"
                                                        :title="row.state === 'available' ? t('agents.sources.connectors.connect_first') : undefined"
                                                        @update:model-value="toggleFeatured(row)"
                                                    />
                                                </TableCell>
                                                <TableCell class="py-4 pe-5" @click.stop>
                                                    <Switch
                                                        v-if="!row.connector.comingSoon"
                                                        :model-value="row.always_available"
                                                        :disabled="row.state !== 'attached'"
                                                        :title="t('agents.sources.connectors.always_hint')"
                                                        @update:model-value="toggleFeaturedAlways(row)"
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
