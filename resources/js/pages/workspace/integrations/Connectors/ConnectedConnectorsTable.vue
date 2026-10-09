<script setup lang="ts">
/**
 * Table of connectors the workspace has already connected (the "Enabled" and
 * "Custom" sections of the Connectors page). Catalog rows show the
 * connector's logo and translated text; custom servers show their name and
 * URL. Clicking a row opens the detail sheet; the switch toggles the server.
 */
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConnectorText } from '@/composables/useConnectorText';
import { connectorLogoUrl } from '@/lib/connectorLogo';
import type { ConnectorRow } from '@/types/connector';
import { Plug } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

/** Every row has a server: these are connected rows only. */
defineProps<{ rows: ConnectorRow[] }>();
const emit = defineEmits<{
    open: [row: ConnectorRow];
    toggle: [row: ConnectorRow];
}>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();
</script>

<template>
    <div class="rounded-lg border">
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead class="h-11 ps-5">{{ t('workspace.connectors.table.name') }}</TableHead>
                    <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.tools') }}</TableHead>
                    <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.agents') }}</TableHead>
                    <TableHead class="h-11 w-32 pe-5 text-end">{{ t('workspace.connectors.table.enabled') }}</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <TableRow v-for="row in rows" :key="row.server!.external_id" class="hover:bg-muted/40 cursor-pointer" @click="emit('open', row)">
                    <TableCell class="py-4 ps-5">
                        <div class="flex items-center gap-4">
                            <div
                                v-if="row.connector"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                                :style="{ backgroundColor: '#' + row.connector.brandColor + '15' }"
                            >
                                <img :src="connectorLogoUrl(row.connector)" :alt="row.connector.name" class="h-5 w-5" />
                            </div>
                            <div v-else class="bg-muted flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                <Plug class="text-muted-foreground h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="leading-tight font-medium">
                                    {{ row.connector ? connectorName(row.connector) : row.server!.name }}
                                </p>
                                <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                    {{ row.connector ? connectorDescription(row.connector) : row.server!.url }}
                                </p>
                            </div>
                        </div>
                    </TableCell>
                    <TableCell class="py-4 text-sm">{{ row.server!.catalog_tools_count }}</TableCell>
                    <TableCell class="py-4 text-sm">{{ row.server!.agents_count }}</TableCell>
                    <TableCell class="py-4 pe-5 text-end" @click.stop>
                        <Switch :model-value="row.server!.is_enabled" @update:model-value="emit('toggle', row)" />
                    </TableCell>
                </TableRow>
            </TableBody>
        </Table>
    </div>
</template>
