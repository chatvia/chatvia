<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ToolApprovalCall } from '@/types/stream';
import { Check, Loader2, Plug, ShieldQuestion, Webhook, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    call: ToolApprovalCall;
    resolving?: boolean;
    isRTL?: boolean;
}>();

const emit = defineEmits<{
    approve: [call: ToolApprovalCall];
    deny: [call: ToolApprovalCall];
}>();

const { t } = useI18n();

// brand_color arrives as a hex, sometimes without the leading '#'. It's a
// literal colour (not a theme token), so an inline style is correct here.
const brandColor = computed(() => {
    const c = props.call.brand_color;
    if (!c) return null;
    return c.startsWith('#') ? c : `#${c}`;
});

const iconStyle = computed(() =>
    brandColor.value ? { boxShadow: `inset 0 0 0 1.5px ${brandColor.value}33`, background: `${brandColor.value}14` } : {},
);

// The specific tool/function being invoked, shown as a readable title. For MCP
// it's the `mcp__{slug}__tool` name with the prefix stripped; for functions the
// raw name. Either way snake/kebab case is humanized: create_coupon → "Create
// Coupon".
const toolName = computed(() => {
    const n = props.call.name || props.call.label || '';
    let raw = n;
    if (n.startsWith('mcp__')) {
        const rest = n.slice(5);
        const idx = rest.indexOf('__');
        raw = idx >= 0 ? rest.slice(idx + 2) : rest;
    }
    const title = raw
        .replace(/[_-]+/g, ' ')
        .trim()
        .replace(/\s+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
    return title || raw;
});

// Where the tool comes from — the connector name for MCP, otherwise "Function".
const provider = computed(() => {
    if (props.call.kind === 'mcp') return props.call.label || t('approval.mcp_tool');
    return props.call.label && props.call.label !== toolName.value ? props.call.label : t('approval.function');
});
</script>

<template>
    <div class="border-border bg-card my-2 w-full max-w-sm overflow-hidden rounded-xl border shadow-sm" :dir="isRTL ? 'rtl' : 'ltr'">
        <!-- Header: branded icon + the tool being called + its source + badge -->
        <div class="flex items-center gap-2.5 px-3.5 pt-3 pb-2">
            <div class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-lg" :style="iconStyle">
                <img v-if="call.icon_url" :src="call.icon_url" :alt="provider" class="h-full w-full object-contain p-1" />
                <Webhook v-else-if="call.kind === 'function'" class="text-muted-foreground h-3.5 w-3.5" />
                <Plug v-else class="text-muted-foreground h-3.5 w-3.5" />
            </div>
            <div class="min-w-0 flex-1 leading-tight">
                <div class="text-foreground truncate text-sm font-semibold">{{ toolName }}</div>
                <div class="text-muted-foreground truncate text-xs">{{ provider }}</div>
            </div>
            <Badge
                variant="outline"
                class="shrink-0 gap-1 border-amber-400/40 bg-amber-400/10 px-1.5 py-0.5 text-[10px] text-amber-600 dark:text-amber-400"
            >
                <ShieldQuestion class="h-3 w-3" />
                {{ t('approval.badge') }}
            </Badge>
        </div>

        <!-- The clear call to action -->
        <p class="text-foreground/90 px-3.5 pb-2.5 text-sm">{{ t('approval.prompt') }}</p>

        <!-- Footer: deny/approve actions -->
        <div class="border-border/60 bg-muted/30 flex items-center justify-end gap-1.5 border-t px-3 py-2">
            <Button
                variant="ghost"
                size="sm"
                class="text-muted-foreground hover:text-foreground h-7 gap-1.5 px-2.5"
                :disabled="resolving"
                @click="emit('deny', call)"
            >
                <X class="h-3.5 w-3.5" />
                {{ t('approval.deny') }}
            </Button>
            <Button size="sm" class="h-7 gap-1.5 px-3" :disabled="resolving" @click="emit('approve', call)">
                <Loader2 v-if="resolving" class="h-3.5 w-3.5 animate-spin" />
                <Check v-else class="h-3.5 w-3.5" />
                {{ t('approval.approve') }}
            </Button>
        </div>
    </div>
</template>
