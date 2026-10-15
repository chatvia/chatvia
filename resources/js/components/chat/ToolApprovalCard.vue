<script setup lang="ts">
/**
 * Approve/Deny card for a tool call the server paused because it can make
 * changes. Says which tool wants to run and where, keeps the exact inputs it
 * will run with behind "Show details", and collapses to an "Approved" / "Denied" status once the
 * visitor decides, so a turn with several gated calls shows which are done.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import type { ToolApprovalCall } from '@/types/stream';
import { Check, ChevronDown, Loader2, Plug, ShieldAlert, Webhook, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    call: ToolApprovalCall;
    resolving?: boolean;
}>();

const emit = defineEmits<{
    approve: [call: ToolApprovalCall];
    deny: [call: ToolApprovalCall];
}>();

const { t } = useI18n();

interface ArgRow {
    key: string;
    label: string;
    value: string;
    /** Objects and arrays render as compact JSON in a monospace font. */
    code: boolean;
}

// The card keeps the visitor's own choice: with several gated calls in one
// checkpoint the server only resumes once every call is decided, and until
// then this card has to show it was answered.
const decision = ref<'approve' | 'deny' | null>(null);
const detailsOpen = ref(false);

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

function words(raw: string): string {
    return raw
        .replace(/([a-z\d])([A-Z])/g, '$1 $2')
        .replace(/[_-]+/g, ' ')
        .trim()
        .replace(/\s+/g, ' ');
}

// The specific tool/function being invoked, shown as a readable title. A
// function's label is the display name its owner chose, so it wins; otherwise
// the name is humanized (`mcp__{slug}__` prefix stripped): create_coupon →
// "Create Coupon".
const toolName = computed(() => {
    if (props.call.kind === 'function' && props.call.label) return props.call.label;

    const n = props.call.name || props.call.label || '';
    let raw = n;
    if (n.startsWith('mcp__')) {
        const rest = n.slice(5);
        const idx = rest.indexOf('__');
        raw = idx >= 0 ? rest.slice(idx + 2) : rest;
    }
    return words(raw).replace(/\b\w/g, (c) => c.toUpperCase()) || raw;
});

// Where the tool comes from — the connector name for MCP, otherwise "Function".
const provider = computed(() => (props.call.kind === 'mcp' ? props.call.label || t('approval.mcp_tool') : t('approval.function')));

// Translated here rather than showing `call.reason`: the server sends one fixed
// English sentence, and naming the connector says where the change happens.
const explanation = computed(() =>
    props.call.kind === 'mcp' && props.call.label ? t('approval.explain_in', { provider: props.call.label }) : t('approval.explain'),
);

const parsedArgs = computed<{ ok: true; value: unknown } | { ok: false }>(() => {
    if (!props.call.args) return { ok: false };
    try {
        return { ok: true, value: JSON.parse(props.call.args) };
    } catch {
        return { ok: false };
    }
});

function isPlainObject(value: unknown): value is Record<string, unknown> {
    return value !== null && typeof value === 'object' && !Array.isArray(value);
}

// The exact inputs the tool will run with, one row per top-level field, so the
// visitor can check the amount / target before approving a write.
const argRows = computed<ArgRow[]>(() => {
    const parsed = parsedArgs.value;
    if (!parsed.ok || !isPlainObject(parsed.value)) return [];

    return Object.entries(parsed.value).map(([key, value]) => {
        const label = words(key);
        const row = { key, label: label.charAt(0).toUpperCase() + label.slice(1) };
        if (value === null || value === undefined || value === '') return { ...row, value: '—', code: false };
        if (typeof value === 'object') return { ...row, value: JSON.stringify(value), code: true };
        return { ...row, value: String(value), code: false };
    });
});

// Inputs that are not a JSON object (a bare array or value, or invalid JSON)
// are shown as-is so the visitor still sees what will run.
const rawArgs = computed(() => {
    const parsed = parsedArgs.value;
    if (!props.call.args) return '';
    if (!parsed.ok) return props.call.args;
    return isPlainObject(parsed.value) ? '' : JSON.stringify(parsed.value, null, 2);
});

const status = computed(() => {
    if (decision.value === 'approve') return { text: t('approval.approved'), icon: Check, tone: 'approved' as const };
    if (decision.value === 'deny') return { text: t('approval.denied'), icon: X, tone: 'denied' as const };
    return { text: t('approval.badge'), icon: ShieldAlert, tone: 'pending' as const };
});

const statusClass = computed(
    () =>
        ({
            pending: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
            approved: 'border-green-600/30 bg-green-600/10 text-green-700 dark:text-green-400',
            denied: 'border-border bg-muted text-muted-foreground',
        })[status.value.tone],
);

function choose(choice: 'approve' | 'deny') {
    if (decision.value || props.resolving) return;
    decision.value = choice;
    if (choice === 'approve') emit('approve', props.call);
    else emit('deny', props.call);
}
</script>

<template>
    <div
        role="group"
        :aria-labelledby="`tool-approval-${call.tool_call_id}`"
        class="border-border bg-card my-2 w-full max-w-sm overflow-hidden rounded-xl border shadow-sm"
    >
        <!-- Header: branded icon, the tool and where it runs, and the live status -->
        <div class="flex items-center gap-3 p-3.5">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg" :style="iconStyle">
                <img v-if="call.icon_url" :src="call.icon_url" :alt="provider" class="h-full w-full object-contain p-1.5" />
                <Webhook v-else-if="call.kind === 'function'" class="text-muted-foreground h-4 w-4" />
                <Plug v-else class="text-muted-foreground h-4 w-4" />
            </div>
            <div class="min-w-0 flex-1 leading-tight">
                <p :id="`tool-approval-${call.tool_call_id}`" class="text-foreground truncate text-sm font-semibold" :title="toolName">
                    {{ toolName }}
                </p>
                <p class="text-muted-foreground mt-0.5 truncate text-xs">{{ provider }}</p>
            </div>
            <Badge variant="outline" class="shrink-0 gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusClass" aria-live="polite">
                <Loader2 v-if="decision && resolving" class="h-3 w-3 animate-spin" />
                <component :is="status.icon" v-else class="h-3 w-3" />
                {{ status.text }}
            </Badge>
        </div>

        <!-- Once decided the header alone tells the story; the rest folds away. -->
        <template v-if="!decision">
            <p class="text-muted-foreground px-3.5 text-sm leading-relaxed">{{ explanation }}</p>

            <!-- What will actually run, field by field, hidden until asked for so
                 the card stays short. -->
            <Collapsible v-if="argRows.length || rawArgs" v-model:open="detailsOpen" class="px-3.5 pt-2.5">
                <CollapsibleTrigger class="group text-muted-foreground hover:text-foreground flex items-center gap-1 text-xs font-medium">
                    {{ detailsOpen ? t('approval.hide_details') : t('approval.show_details') }}
                    <ChevronDown class="h-3.5 w-3.5 transition-transform group-data-[state=open]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <!-- Values are never clipped: a message body is exactly what the
                         visitor has to read before approving it. The list scrolls instead. -->
                    <dl v-if="argRows.length" class="bg-muted/40 divide-border/60 mt-2 max-h-64 divide-y overflow-y-auto rounded-lg border">
                        <div v-for="row in argRows" :key="row.key" class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-3 px-3 py-2 text-xs">
                            <dt class="text-muted-foreground break-words" :title="row.key">{{ row.label }}</dt>
                            <dd
                                dir="auto"
                                class="text-foreground text-start break-words whitespace-pre-line"
                                :class="row.code && 'font-mono text-[11px]'"
                            >
                                {{ row.value }}
                            </dd>
                        </div>
                    </dl>
                    <pre
                        v-else
                        dir="ltr"
                        class="bg-muted/40 text-foreground/80 mt-2 max-h-40 overflow-auto rounded-lg border p-3 text-[11px] leading-relaxed"
                    ><code>{{ rawArgs }}</code></pre>
                </CollapsibleContent>
            </Collapsible>

            <div class="flex gap-2 p-3.5">
                <Button variant="outline" size="sm" class="h-8 flex-1 gap-1.5" :disabled="resolving" @click="choose('deny')">
                    <X class="h-3.5 w-3.5" />
                    {{ t('approval.deny') }}
                </Button>
                <Button size="sm" class="h-8 flex-1 gap-1.5" :disabled="resolving" @click="choose('approve')">
                    <Check class="h-3.5 w-3.5" />
                    {{ t('approval.approve') }}
                </Button>
            </div>
        </template>
    </div>
</template>
