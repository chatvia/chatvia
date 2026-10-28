<script setup lang="ts">
/**
 * One tool in the connector sheet's Tools tab: its title, the permission
 * control, and a "View details" toggle that reveals the function name,
 * description and highlighted JSON input schema.
 */
import { highlightJson } from '@/dashboard/workspace/lib/highlightJson';
import type { McpTool, McpToolPermission } from '@/shared/types/connector';
import { ChevronRight } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import ToolPermissionControl from './ToolPermissionControl.vue';

defineProps<{ tool: McpTool; expanded: boolean }>();
const emit = defineEmits<{
    'toggle-details': [];
    'set-permission': [permission: McpToolPermission];
}>();

const { t } = useI18n();

// `create_event` → `Create Event`. Matches Laravel's Str::title for snake/
// kebab-cased tool names; non-separated camelCase keeps its existing casing.
function toTitle(name: string): string {
    return name.replace(/[_-]+/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function highlightSchema(parameters: unknown): string {
    return highlightJson(JSON.stringify(parameters, null, 2) ?? '');
}
</script>

<template>
    <div class="py-4 first:pt-0 last:pb-0">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-medium" :class="{ 'text-muted-foreground line-through': tool.permission === 'blocked' }">
                    {{ toTitle(tool.short_name) }}
                </p>
            </div>
            <ToolPermissionControl v-if="tool.id !== undefined" :permission="tool.permission" @select="(value) => emit('set-permission', value)" />
        </div>
        <button class="text-muted-foreground hover:text-foreground mt-3 inline-flex items-center gap-1 text-xs" @click="emit('toggle-details')">
            <ChevronRight class="h-3 w-3 transition-transform" :class="{ 'rotate-90': expanded }" />
            {{ t('shared.connector_sheet.view_details') }}
        </button>
        <div v-if="expanded" class="mt-3 space-y-3">
            <div>
                <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {{ t('shared.connector_sheet.function') }}
                </p>
                <code class="mt-1 block font-mono text-xs break-all">{{ tool.short_name }}</code>
            </div>
            <div>
                <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {{ t('shared.connector_sheet.description') }}
                </p>
                <p class="mt-1 text-sm leading-relaxed">{{ tool.description }}</p>
            </div>
            <div>
                <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {{ t('shared.connector_sheet.input_schema') }}
                </p>
                <!-- highlightJson escapes the schema itself, so v-html is safe here. -->
                <pre
                    class="bg-muted mt-1 overflow-x-auto rounded p-3 font-mono text-xs leading-relaxed"
                ><code class="hljs language-json" v-html="highlightSchema(tool.parameters)"></code></pre>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Highlight.js JSON colors — VS Code light palette by default, "dark+"
   palette in dark mode. Mirrors JsonCodeEditor so highlighted JSON looks
   the same across the app. */
:deep(.hljs) {
    color: var(--foreground);
    background: transparent;
}
:deep(.hljs-attr) {
    color: #0451a5;
}
:deep(.hljs-string) {
    color: #a31515;
}
:deep(.hljs-number),
:deep(.hljs-literal) {
    color: #098658;
}
:deep(.hljs-punctuation) {
    color: var(--muted-foreground);
}

:global(html.dark) :deep(.hljs-attr) {
    color: #9cdcfe;
}
:global(html.dark) :deep(.hljs-string) {
    color: #ce9178;
}
:global(html.dark) :deep(.hljs-number),
:global(html.dark) :deep(.hljs-literal) {
    color: #b5cea8;
}
</style>
