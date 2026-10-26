<script setup lang="ts">
/**
 * Table of featured catalog connectors that are not enabled yet. A row shows
 * the enable switch once the workspace has a server for it, a Connect button
 * otherwise, and a "Coming soon" badge (with no row click) for connectors that
 * cannot be connected yet.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConnectorText } from '@/composables/useConnectorText';
import { connectorLogoUrl } from '@/lib/connectorLogo';
import type { Connector, ConnectorRow } from '@/types/connector';
import { useI18n } from 'vue-i18n';

/** Every row has a catalog connector. */
defineProps<{ rows: ConnectorRow[] }>();
const emit = defineEmits<{
    open: [row: ConnectorRow];
    toggle: [row: ConnectorRow];
    connect: [connector: Connector];
}>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

function isRowActionable(row: ConnectorRow): boolean {
    return row.connector === null || !row.connector.comingSoon;
}
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead class="h-11 ps-5">{{ t('workspace.connectors.table.name') }}</TableHead>
                <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.tools') }}</TableHead>
                <TableHead class="h-11 w-32">{{ t('workspace.connectors.table.agents') }}</TableHead>
                <TableHead class="h-11 w-40 pe-5 text-end">{{ t('workspace.connectors.table.enabled') }}</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow
                v-for="row in rows"
                :key="row.connector!.slug"
                :class="isRowActionable(row) ? 'hover:bg-muted/40 cursor-pointer' : 'cursor-default'"
                @click="emit('open', row)"
            >
                <TableCell class="py-4 ps-5">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                            :style="{ backgroundColor: '#' + row.connector!.brandColor + '15' }"
                        >
                            <img :src="connectorLogoUrl(row.connector)" :alt="row.connector!.name" class="h-5 w-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="leading-tight font-medium">{{ connectorName(row.connector!) }}</p>
                            <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">
                                {{ connectorDescription(row.connector!) }}
                            </p>
                        </div>
                    </div>
                </TableCell>
                <TableCell class="py-4 text-sm">{{ row.server?.catalog_tools_count ?? '—' }}</TableCell>
                <TableCell class="py-4 text-sm">{{ row.server?.agents_count ?? '—' }}</TableCell>
                <TableCell class="py-4 pe-5 text-end" @click.stop>
                    <Switch v-if="row.server" :model-value="row.server.is_enabled" @update:model-value="emit('toggle', row)" />
                    <Button v-else-if="!row.connector!.comingSoon" size="sm" variant="outline" @click="emit('connect', row.connector!)">
                        {{ t('workspace.common.connect') }}
                    </Button>
                    <Badge v-else variant="outline">{{ t('workspace.connectors.coming_soon') }}</Badge>
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
