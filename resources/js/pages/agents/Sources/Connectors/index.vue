<script lang="ts" setup>
import AgentConnectorTable from '@/components/agents/connectors/AgentConnectorTable.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { TableCell, TableRow } from '@/components/ui/table';
import { useAgentConnectors } from '@/composables/agents/connectors/useAgentConnectors';
import { useConnectorText } from '@/composables/useConnectorText';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { connectorLogoUrl } from '@/lib/connectorLogo';
import { edit as editConnectors } from '@/routes/integrations/connectors';
import { LoaderCircle, Plug, Search } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

// Row states from the server:
//   attached   — the workspace has the connector and this agent uses it
//   unattached — the workspace has the connector, this agent does not use it
//   available  — not connected in the workspace yet (connect it in Settings)
const { loading, search, filteredFeatured, filteredCustom, toggleFeatured, toggleCustom, toggleFeaturedAlways, toggleCustomAlways } =
    useAgentConnectors(() => props.agent.external_id);

const alwaysLabel = (name: string): string => `${name} — ${t('agents.sources.connectors.column_always')}`;
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
                                    <a :href="editConnectors.url()" class="underline">{{ t('agents.sources.connectors.settings_link') }}</a
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
                            <!-- The workspace's own MCP servers -->
                            <section v-if="filteredCustom.length > 0">
                                <h2 class="mb-3 text-base font-semibold">{{ t('agents.sources.connectors.custom_title') }}</h2>
                                <AgentConnectorTable>
                                    <TableRow v-for="row in filteredCustom" :key="row.external_id" class="cursor-pointer" @click="toggleCustom(row)">
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
                                            <Switch :model-value="row.is_attached" :aria-label="row.name" @update:model-value="toggleCustom(row)" />
                                        </TableCell>
                                        <TableCell class="py-4 pe-5" @click.stop>
                                            <Switch
                                                :model-value="row.always_available"
                                                :disabled="!row.is_attached"
                                                :aria-label="alwaysLabel(row.name)"
                                                @update:model-value="toggleCustomAlways(row)"
                                            />
                                        </TableCell>
                                    </TableRow>
                                </AgentConnectorTable>
                            </section>

                            <!-- Catalog connectors -->
                            <section>
                                <div v-if="filteredFeatured.length === 0" class="text-muted-foreground rounded-md border py-12 text-center text-sm">
                                    {{ t('agents.sources.connectors.no_match') }}
                                </div>
                                <AgentConnectorTable v-else>
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
                                                    <img :src="connectorLogoUrl(row.connector)" :alt="row.connector.name" class="h-5 w-5" />
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
                                            <Badge v-if="row.connector.comingSoon" variant="outline">{{ t('agents.sources.connectors.soon') }}</Badge>
                                            <Switch
                                                v-else
                                                :model-value="row.state === 'attached'"
                                                :disabled="row.state === 'available'"
                                                :title="row.state === 'available' ? t('agents.sources.connectors.connect_first') : undefined"
                                                :aria-label="connectorName(row.connector)"
                                                @update:model-value="toggleFeatured(row)"
                                            />
                                        </TableCell>
                                        <TableCell class="py-4 pe-5" @click.stop>
                                            <Switch
                                                v-if="!row.connector.comingSoon"
                                                :model-value="row.always_available"
                                                :disabled="row.state !== 'attached'"
                                                :aria-label="alwaysLabel(connectorName(row.connector))"
                                                @update:model-value="toggleFeaturedAlways(row)"
                                            />
                                        </TableCell>
                                    </TableRow>
                                </AgentConnectorTable>
                            </section>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AgentLayout>
</template>
