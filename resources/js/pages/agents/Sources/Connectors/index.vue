<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AgentLayout from '@/layouts/AgentLayout.vue';
import type { AgentMcpServer, Connector } from '@/types/connector';
import axios from 'axios';
import { LoaderCircle, Plug, Search } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: { external_id: string; name: string };
}

const props = defineProps<Props>();

// Featured row state from the backend:
//   - attached   — account-level row exists AND attached to this agent
//   - unattached — account-level row exists, not attached to this agent
//   - available  — no account-level row yet (connect it in Settings)
type FeaturedState = 'attached' | 'unattached' | 'available';

interface FeaturedRow {
    connector: Connector;
    state: FeaturedState;
    server: AgentMcpServer | null;
}

interface CustomRow extends AgentMcpServer {
    is_attached: boolean;
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

const filteredFeatured = computed(() =>
    featured.value
        .filter((f) => f.connector.provider !== 'chatvia')
        .filter((f) => matches({ name: f.connector.name, description: f.connector.description })),
);
const filteredChatvia = computed(() =>
    featured.value
        .filter((f) => f.connector.provider === 'chatvia')
        .filter((f) => matches({ name: f.connector.name, description: f.connector.description })),
);
const filteredCustom = computed(() => customConnectors.value.filter(matches));
const activeTab = ref<'featured' | 'chatvia'>('featured');

async function fetchAll(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get(route('agents.sources.connectors.data', props.agent.external_id));
        featured.value = response.data.featured;
        customConnectors.value = response.data.custom;
    } catch (err) {
        console.error(err);
        toast.error('Failed to load connectors');
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
            if (i !== -1) featured.value[i] = { ...featured.value[i], state: 'unattached' };
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
            if (i !== -1) customConnectors.value[i] = { ...customConnectors.value[i], is_attached: false };
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
        toast.error('Failed to attach');
    }
}

async function detach(slug: string, onSuccess: () => void): Promise<void> {
    try {
        await axios.delete(route('agents.sources.connectors.detach', { agent: props.agent.external_id, slug }));
        onSuccess();
    } catch (err) {
        console.error(err);
        toast.error('Failed to detach');
    }
}

onMounted(fetchAll);
</script>

<template>
    <AgentLayout :agent="agent" title="Connectors">
        <div class="flex justify-center">
            <div class="w-full max-w-6xl space-y-6">
                <Card>
                    <CardHeader>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <CardTitle class="flex items-center gap-2">
                                    <Plug class="h-5 w-5" />
                                    Connectors
                                </CardTitle>
                                <CardDescription>
                                    Attach account-level connectors to this agent. Connect or add new ones in
                                    <a :href="route('settings.connectors.edit')" class="underline">Settings → Connectors</a>.
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="relative max-w-sm">
                            <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                            <Input v-model="search" placeholder="Search connectors" class="pl-9" />
                        </div>

                        <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-12 text-sm">
                            <LoaderCircle class="h-4 w-4 animate-spin" />
                            Loading connectors
                        </div>

                        <div v-else class="space-y-8">
                            <section v-if="filteredCustom.length > 0">
                                <h2 class="mb-3 text-base font-semibold">Custom connectors</h2>
                                <div class="rounded-lg border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="h-11 pl-5">Connector</TableHead>
                                                <TableHead class="h-11 w-32">Tools</TableHead>
                                                <TableHead class="h-11 w-32 pr-5">Enabled</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow v-for="row in filteredCustom" :key="row.external_id">
                                                <TableCell class="py-4 pl-5">
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
                                                <TableCell class="py-4 pr-5">
                                                    <Switch :model-value="row.is_attached" @update:model-value="toggleCustom(row)" />
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>
                            </section>

                            <Tabs v-model="activeTab">
                                <TabsList class="grid w-full max-w-md grid-cols-2">
                                    <TabsTrigger value="featured">
                                        Featured connectors
                                        <span class="text-muted-foreground ml-1 text-xs">({{ filteredFeatured.length }})</span>
                                    </TabsTrigger>
                                    <TabsTrigger value="chatvia">
                                        Chatvia connectors
                                        <span class="text-muted-foreground ml-1 text-xs">({{ filteredChatvia.length }})</span>
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="featured" class="mt-4">
                                    <div
                                        v-if="filteredFeatured.length === 0"
                                        class="text-muted-foreground rounded-md border py-12 text-center text-sm"
                                    >
                                        No featured connectors match.
                                    </div>
                                    <div v-else class="rounded-lg border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead class="h-11 pl-5">Connector</TableHead>
                                                    <TableHead class="h-11 w-32">Tools</TableHead>
                                                    <TableHead class="h-11 w-32 pr-5">Enabled</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow v-for="row in filteredFeatured" :key="row.connector.slug">
                                                    <TableCell class="py-4 pl-5">
                                                        <div class="flex items-center gap-4">
                                                            <div
                                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                                                :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                                                            >
                                                                <img :src="logoUrl(row.connector)" :alt="row.connector.name" class="h-5 w-5" />
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="leading-tight font-medium">{{ row.connector.name }}</p>
                                                                <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                                                    {{ row.connector.description }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell class="py-4 text-sm">
                                                        {{ row.server ? row.server.catalog_tools_count : '—' }}
                                                    </TableCell>
                                                    <TableCell class="py-4 pr-5">
                                                        <Badge v-if="row.connector.comingSoon" variant="outline">Soon</Badge>
                                                        <Switch
                                                            v-else
                                                            :model-value="row.state === 'attached'"
                                                            :disabled="row.state === 'available'"
                                                            :title="row.state === 'available' ? 'Connect this MCP in Settings first' : undefined"
                                                            @update:model-value="toggleFeatured(row)"
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </TabsContent>

                                <TabsContent value="chatvia" class="mt-4">
                                    <div
                                        v-if="filteredChatvia.length === 0"
                                        class="text-muted-foreground rounded-md border py-12 text-center text-sm"
                                    >
                                        No Chatvia connectors match.
                                    </div>
                                    <div v-else class="rounded-lg border">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead class="h-11 pl-5">Connector</TableHead>
                                                    <TableHead class="h-11 w-32">Tools</TableHead>
                                                    <TableHead class="h-11 w-32 pr-5">Enabled</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                <TableRow v-for="row in filteredChatvia" :key="row.connector.slug">
                                                    <TableCell class="py-4 pl-5">
                                                        <div class="flex items-center gap-4">
                                                            <div
                                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                                                :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                                                            >
                                                                <img :src="logoUrl(row.connector)" :alt="row.connector.name" class="h-5 w-5" />
                                                            </div>
                                                            <div class="min-w-0">
                                                                <p class="leading-tight font-medium">{{ row.connector.name }}</p>
                                                                <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                                                    {{ row.connector.description }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell class="py-4 text-sm">
                                                        {{ row.server ? row.server.catalog_tools_count : '—' }}
                                                    </TableCell>
                                                    <TableCell class="py-4 pr-5">
                                                        <Badge v-if="row.connector.comingSoon" variant="outline">Soon</Badge>
                                                        <Switch
                                                            v-else
                                                            :model-value="row.state === 'attached'"
                                                            :disabled="row.state === 'available'"
                                                            :title="row.state === 'available' ? 'Connect this MCP in Settings first' : undefined"
                                                            @update:model-value="toggleFeatured(row)"
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
