<script setup lang="ts">
/**
 * Tools tab of the connector detail sheet: refresh the server's catalog,
 * search it, and set per-tool or per-section permissions. Purely
 * presentational — the state lives in `useConnectorTools`, owned by the sheet.
 */
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { TabsContent } from '@/shared/ui/tabs';
import type { ToolSection } from '@/dashboard/workspace/composables/useConnectorTools';
import type { McpTool, McpToolPermission, McpToolScope } from '@/shared/types/connector';
import { LoaderCircle, RefreshCw, Search } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import ConnectorToolSection from './ConnectorToolSection.vue';

defineProps<{
    /** Every tool of the server, before the search filter. */
    tools: McpTool[];
    /** How many tools match the search. */
    matchCount: number;
    sections: ToolSection[];
    sectionsOpen: Record<McpToolScope, boolean>;
    /** Which tools have their details expanded, by tool name. */
    expanded: Record<string, boolean>;
    loading: boolean;
    refreshing: boolean;
}>();
const emit = defineEmits<{
    refresh: [];
    'section-open': [scope: McpToolScope, value: boolean];
    'toggle-details': [toolName: string];
    'set-permission': [tool: McpTool, permission: McpToolPermission];
    'apply-section': [scope: McpToolScope, permission: McpToolPermission];
}>();
const search = defineModel<string>('search', { required: true });

const { t } = useI18n();
</script>

<template>
    <TabsContent value="tools" class="space-y-4 pt-6">
        <Button variant="outline" class="w-full" :disabled="refreshing" @click="emit('refresh')">
            <RefreshCw class="me-2 h-4 w-4" :class="{ 'animate-spin': refreshing }" />
            {{ t('shared.connector_sheet.refresh_tools') }}
        </Button>

        <div v-if="loading" class="text-muted-foreground flex items-center gap-2 py-6 text-sm">
            <LoaderCircle class="h-4 w-4 animate-spin" />
            {{ t('shared.connector_sheet.loading_tools') }}
        </div>

        <div v-else-if="tools.length === 0" class="text-muted-foreground py-6 text-center text-sm">
            {{ t('shared.connector_sheet.no_tools_advertised') }}
        </div>

        <div v-else>
            <div class="relative mb-4">
                <Search class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                <Input v-model="search" :placeholder="t('shared.connector_sheet.search_tools_placeholder')" class="ps-9" />
            </div>

            <div v-if="matchCount === 0" class="text-muted-foreground py-6 text-center text-sm">
                {{ t('shared.connector_sheet.no_tools_match_search') }}
            </div>

            <template v-else>
                <!-- Read-only and Write/delete sections. `scope` comes from the
                     MCP server's `readOnlyHint` annotation at catalog time, so
                     the split mirrors what the server itself declares about
                     each tool's blast radius. -->
                <ConnectorToolSection
                    v-for="section in sections"
                    :key="section.key"
                    :open="sectionsOpen[section.key]"
                    :section="section"
                    :expanded="expanded"
                    @update:open="(value) => emit('section-open', section.key, value)"
                    @toggle-details="(toolName) => emit('toggle-details', toolName)"
                    @set-permission="(tool, permission) => emit('set-permission', tool, permission)"
                    @apply-section="(scope, permission) => emit('apply-section', scope, permission)"
                />
            </template>
        </div>
    </TabsContent>
</template>
