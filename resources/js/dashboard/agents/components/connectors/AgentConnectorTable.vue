<script setup lang="ts">
/**
 * The table frame shared by the custom and catalog lists on the agent's
 * Connectors page: the bordered table and its four column headers. Rows go in
 * the default slot. The "Always on" header carries a tooltip, since the
 * default (the agent picks the tools each message needs) isn't obvious.
 */
import { Table, TableBody, TableHead, TableHeader, TableRow } from '@/shared/ui/table';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { Info } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead class="h-11 ps-5">{{ t('agents.sources.connectors.column_connector') }}</TableHead>
                <TableHead class="h-11 w-32">{{ t('agents.sources.connectors.column_tools') }}</TableHead>
                <TableHead class="h-11 w-32">{{ t('agents.sources.connectors.column_enabled') }}</TableHead>
                <TableHead class="h-11 w-36 pe-5">
                    <div class="flex items-center gap-1.5">
                        {{ t('agents.sources.connectors.column_always') }}
                        <TooltipProvider :delay-duration="0">
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:text-foreground cursor-help"
                                        :aria-label="t('agents.sources.connectors.always_hint')"
                                    >
                                        <Info class="size-3.5" />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="top" class="max-w-xs">
                                    <p>{{ t('agents.sources.connectors.always_hint') }}</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <slot />
        </TableBody>
    </Table>
</template>
