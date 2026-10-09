<script setup lang="ts">
/**
 * The table frame shared by the custom and catalog lists on the agent's
 * Connectors page: the bordered table and its four column headers. Rows go in
 * the default slot.
 */
import { Table, TableBody, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
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
                <slot />
            </TableBody>
        </Table>
    </div>
</template>
