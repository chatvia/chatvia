<script setup lang="ts">
/**
 * One collapsible group of tools (read-only or write/delete) in the connector
 * sheet's Tools tab, with a "Set all" menu that applies one permission to
 * every tool in the group at once.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useToolPermissionOptions, type ToolSection } from '@/composables/useConnectorTools';
import type { McpTool, McpToolPermission, McpToolScope } from '@/types/connector';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import ConnectorToolRow from './ConnectorToolRow.vue';

defineProps<{
    section: ToolSection;
    /** Which tools have their details expanded, by tool name. */
    expanded: Record<string, boolean>;
}>();
const emit = defineEmits<{
    'toggle-details': [toolName: string];
    'set-permission': [tool: McpTool, permission: McpToolPermission];
    'apply-section': [scope: McpToolScope, permission: McpToolPermission];
}>();
const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const permissionOptions = useToolPermissionOptions();
</script>

<template>
    <div class="border-b last:border-b-0">
        <Collapsible v-model:open="open">
            <div class="flex items-center gap-1">
                <CollapsibleTrigger
                    class="hover:bg-muted/40 flex flex-1 items-center gap-2 rounded-md px-2 py-3 transition-colors"
                    :disabled="section.items.length === 0"
                >
                    <ChevronRight v-if="!open" class="text-muted-foreground h-4 w-4" />
                    <ChevronDown v-else class="text-muted-foreground h-4 w-4" />
                    <span class="text-sm font-medium">{{ section.title }}</span>
                    <Badge variant="secondary" class="text-xs">{{ section.items.length }}</Badge>
                </CollapsibleTrigger>

                <!-- Section-level bulk permission: applies one permission to every
                     tool in this scope at once, so the user doesn't have to set
                     them one by one. -->
                <DropdownMenu v-if="section.items.length > 0">
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-foreground h-7 shrink-0 gap-1 px-2 text-xs">
                            {{ t('shared.connector_sheet.set_all') }}
                            <ChevronDown class="h-3.5 w-3.5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem
                            v-for="option in permissionOptions"
                            :key="option.value"
                            class="cursor-pointer"
                            @select="emit('apply-section', section.key, option.value)"
                        >
                            <component :is="option.icon" class="me-2 h-4 w-4" />
                            {{ option.label }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <CollapsibleContent>
                <div v-if="section.items.length === 0" class="text-muted-foreground px-2 py-4 text-center text-xs">
                    {{ t('shared.connector_sheet.section_none') }}
                </div>
                <div v-else class="divide-border divide-y ps-6">
                    <ConnectorToolRow
                        v-for="tool in section.items"
                        :key="tool.name"
                        :tool="tool"
                        :expanded="expanded[tool.name] === true"
                        @toggle-details="emit('toggle-details', tool.name)"
                        @set-permission="(permission) => emit('set-permission', tool, permission)"
                    />
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
