<script lang="ts" setup>
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import xml from 'highlight.js/lib/languages/xml';
import {
    Check,
    Command,
    Copy,
    ExternalLink,
    Globe,
    Info,
    MessageCircle,
    MoveHorizontal,
    MoveVertical,
    Save,
    Settings2,
    Shield,
    Terminal,
    Zap,
} from 'lucide-vue-next';
import { computed, reactive, ref, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

hljs.registerLanguage('xml', xml);
hljs.registerLanguage('javascript', javascript);

interface AgentData {
    external_id: string;
    name: string;
    is_public: boolean;
    chat_url: string;
    chat_embed_url: string;
    permanent_url: string;
    url: string;
}
type Settings = Record<string, unknown> | null | undefined;

const props = defineProps<{
    agent: AgentData;
    authToken: string | null;
    embedScriptUrl: string;
    widgetSettings: Settings;
    defaultWidget: Settings;
    barSettings: Settings;
    defaultBar: Settings;
    surfaces: Settings;
}>();

const { t } = useI18n();

// ── Per-surface enable/disable. Each surface defaults ON; only an explicit
// `false` disables it. Toggling posts all three to the settings endpoint
// (group: surfaces); the change is reverted in the UI if the request fails. ──
type SurfaceKey = 'hosted_chat' | 'widget' | 'bar';
const surfaceEnabled = reactive<Record<SurfaceKey, boolean>>({
    hosted_chat: props.surfaces?.hosted_chat !== false,
    widget: props.surfaces?.widget !== false,
    bar: props.surfaces?.bar !== false,
});
const savingSurfaces = ref(false);
const toggleSurface = (key: SurfaceKey, value: boolean): void => {
    surfaceEnabled[key] = value;
    savingSurfaces.value = true;
    router.post(
        route('agents.settings.update', { agent: props.agent.external_id, group: 'surfaces' }),
        { ...surfaceEnabled },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => toast.success(value ? t('agents.deploy.surface_enabled') : t('agents.deploy.surface_disabled')),
            onError: () => {
                surfaceEnabled[key] = !value;
                toast.error(t('agents.deploy.surface_error'));
            },
            onFinish: () => (savingSurfaces.value = false),
        },
    );
};

// Private agents embed with a server-minted JWT — the snippets carry a
// `data-auth` placeholder and we nudge the operator to make it public.
const isProtected = computed(() => !props.agent.is_public);
const shareUrl = computed(() => props.agent.permanent_url || props.agent.url);

const pick = (s: Settings, d: Settings, key: string, fallback: string): string => {
    const v = (s?.[key] ?? d?.[key]) as string | undefined;
    return v != null && v !== '' ? String(v) : fallback;
};

// ── Widget install snippet (reflects the configured theme/language) ──
const widgetSnippet = computed(() => {
    const lines = [
        '<script id="chatbot-embed-script"',
        `    src="${props.embedScriptUrl}"`,
        `    data-agent-id="${props.agent.external_id}"`,
        `    data-language="${pick(props.widgetSettings, props.defaultWidget, 'language', 'auto')}"`,
        `    data-theme="${pick(props.widgetSettings, props.defaultWidget, 'theme', 'auto')}"`,
    ];
    if (isProtected.value) lines.push('    data-auth="YOUR_SIGNED_TOKEN"');
    lines.push('    defer><\/script>');
    return lines.join('\n');
});

// ── Command Bar install snippet ──
// Trigger and placement are not in the snippet: the widget reads them from the
// agent's saved bar settings at load time, so later dashboard changes apply
// without re-pasting the code.
const barSnippet = computed(() => {
    const lines = [
        '<script id="chatbot-embed-script"',
        `    src="${props.embedScriptUrl}"`,
        `    data-agent-id="${props.agent.external_id}"`,
        '    data-mode="bar"',
    ];
    if (isProtected.value) lines.push('    data-auth="YOUR_SIGNED_TOKEN"');
    lines.push('    defer><\/script>');
    return lines.join('\n');
});

// ── Hosted Chat iframe configurator ──
const iframeOptions = reactive({ width: '100%', height: '600px', title: '' });
watchEffect(() => {
    iframeOptions.title = t('agents.deploy.chat.iframe_title', { name: props.agent.name });
});
const setPreset = (p: 'full' | 'large' | 'medium' | 'small'): void => {
    const sizes = {
        full: ['100%', '100vh'],
        large: ['800px', '600px'],
        medium: ['600px', '500px'],
        small: ['400px', '400px'],
    } as const;
    [iframeOptions.width, iframeOptions.height] = sizes[p];
};
const iframeCode = computed(
    () =>
        `<iframe src="${props.agent.chat_embed_url}" width="${iframeOptions.width}" height="${iframeOptions.height}" title="${iframeOptions.title}" frameborder="0" allowfullscreen loading="lazy"></iframe>`,
);

const hl = (code: string): string => hljs.highlight(code, { language: 'xml', ignoreIllegals: true }).value;
const hlWidget = computed(() => hl(widgetSnippet.value));
const hlBar = computed(() => hl(barSnippet.value));
const hlIframe = computed(() => hl(iframeCode.value));

// One copied-state keyed by section so each button flips independently.
const copiedKey = ref<string | null>(null);
const copy = async (text: string, key: string): Promise<void> => {
    try {
        await navigator.clipboard.writeText(text);
        copiedKey.value = key;
        toast.success(t('agents.deploy.copied'));
        setTimeout(() => {
            if (copiedKey.value === key) copiedKey.value = null;
        }, 2000);
    } catch {
        toast.error(t('agents.deploy.copy_failed'));
    }
};

const setupUrl = computed(() => route('agents.setup', { agent: props.agent.external_id }));
const securityUrl = computed(() => route('agents.identity', { agent: props.agent.external_id }));

// Allowed sites — the widget's origin whitelist. A deployment/security concern,
// so it's editable right here. Only the `sites` key is posted; the settings
// endpoint merges per-key, leaving the rest of the widget config untouched.
const initialSites = (): string[] => {
    const s = (props.widgetSettings?.sites ?? props.defaultWidget?.sites) as string[] | undefined;
    return Array.isArray(s) ? [...s] : [];
};
const sitesForm = useForm<{ sites: string[] }>({ sites: initialSites() });
const sitesText = computed({
    get: () => sitesForm.sites.join('\n'),
    set: (value: string) => {
        sitesForm.sites = value
            .split(/\n+/)
            .map((s) => s.trim())
            .filter(Boolean);
    },
});
const saveSites = (): void => {
    sitesForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.deploy.sites_saved')),
        onError: () => toast.error(t('agents.deploy.sites_error')),
    });
};

// ── JavaScript API docs — the Widget and Bar both expose `window.chatvia.*` on
// the host page, so this dialog is opened from both sections. ──
const showApiDocsDialog = ref(false);
const apiMethods = computed(() => [
    { method: 'window.chatvia.open()', description: t('agents.setup.api.methods_list.open') },
    { method: 'window.chatvia.close()', description: t('agents.setup.api.methods_list.close') },
    { method: 'window.chatvia.toggle()', description: t('agents.setup.api.methods_list.toggle') },
    { method: 'window.chatvia.isOpen()', description: t('agents.setup.api.methods_list.is_open') },
    { method: 'window.chatvia.send(msg)', description: t('agents.setup.api.methods_list.send') },
    { method: 'window.chatvia.newChat()', description: t('agents.setup.api.methods_list.new_chat') },
    { method: 'window.chatvia.destroy()', description: t('agents.setup.api.methods_list.destroy') },
    { method: 'window.chatvia.on(event, fn)', description: t('agents.setup.api.methods_list.on') },
    { method: 'window.chatvia.off(event, fn)', description: t('agents.setup.api.methods_list.off') },
]);
const eventsCodeExample = `// When chat window opens
window.chatvia.on('open', () => {
  console.log('Chat opened');
});

// When chat window closes
window.chatvia.on('close', () => {
  console.log('Chat closed');
});

// When widget is ready
window.chatvia.on('ready', () => {
  console.log('Widget initialized');
});

// Unsubscribe from events
const unsubscribe = window.chatvia.on('open', myCallback);
unsubscribe(); // Stop listening

// Or use window.chatvia.off()
window.chatvia.off('open', myCallback); // Remove specific callback
window.chatvia.off('open'); // Remove all callbacks for this event`;
const highlightedEventsExample = computed(() => hljs.highlight(eventsCodeExample, { language: 'javascript', ignoreIllegals: true }).value);
const copyApiCode = async (code: string): Promise<void> => {
    try {
        await navigator.clipboard.writeText(code);
        toast.success(t('agents.deploy.copied'));
    } catch {
        toast.error(t('agents.deploy.copy_failed'));
    }
};
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.deploy.title')" :description="t('agents.deploy.subtitle')">
        <Head :title="t('agents.deploy.head_title', { name: props.agent.name })" />

        <div class="flex justify-center">
            <div class="w-full max-w-3xl space-y-6">
                <!-- Protected-agent nudge -->
                <Alert v-if="isProtected">
                    <Shield class="h-4 w-4" />
                    <AlertTitle>{{ t('agents.deploy.protected_title') }}</AlertTitle>
                    <AlertDescription>
                        {{ t('agents.deploy.protected_description') }}
                        <Link :href="securityUrl" class="text-primary font-medium hover:underline">{{ t('agents.deploy.open_security') }}</Link>
                    </AlertDescription>
                </Alert>

                <!-- ── Widget ── -->
                <section class="bg-card rounded-xl border">
                    <div class="flex items-start gap-4 border-b p-5">
                        <div class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg">
                            <MessageCircle class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">{{ t('agents.deploy.widget.title') }}</h2>
                            <p class="text-muted-foreground mt-0.5 text-sm">{{ t('agents.deploy.widget.description') }}</p>
                        </div>
                        <div class="ms-auto flex shrink-0 items-center gap-2">
                            <span class="text-muted-foreground text-xs">{{
                                surfaceEnabled.widget ? t('agents.deploy.enabled') : t('agents.deploy.disabled')
                            }}</span>
                            <Switch
                                :model-value="surfaceEnabled.widget"
                                :disabled="savingSurfaces"
                                :aria-label="t('agents.deploy.toggle_surface')"
                                @update:model-value="(v) => toggleSurface('widget', v)"
                            />
                        </div>
                    </div>
                    <div class="space-y-4 p-5" :class="{ 'pointer-events-none opacity-50': !surfaceEnabled.widget }">
                        <div class="bg-muted space-y-3 rounded-lg p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-muted-foreground text-sm">{{ t('agents.deploy.install_label') }}</p>
                                <Button type="button" variant="outline" size="sm" class="shrink-0 gap-2" @click="copy(widgetSnippet, 'widget')">
                                    <component :is="copiedKey === 'widget' ? Check : Copy" class="size-4" />
                                    {{ copiedKey === 'widget' ? t('agents.deploy.copied') : t('agents.deploy.copy') }}
                                </Button>
                            </div>
                            <div class="bg-background rounded border p-3" dir="ltr">
                                <pre class="hljs overflow-x-auto bg-transparent p-0 text-xs"><code class="language-xml" v-html="hlWidget" /></pre>
                            </div>
                        </div>

                        <!-- Allowed sites — origin whitelist for the widget -->
                        <div class="space-y-2 border-t pt-4">
                            <Label for="deploy-widget-sites" class="text-sm font-medium">
                                {{ t('agents.setup.behavior.allowed_sites') }}
                                <span class="text-muted-foreground font-normal">{{ t('agents.setup.behavior.allowed_sites_hint') }}</span>
                            </Label>
                            <div class="relative">
                                <div class="text-muted-foreground pointer-events-none absolute start-0 top-0 flex items-center ps-3 pt-2.5">
                                    <Globe class="size-4" />
                                </div>
                                <textarea
                                    id="deploy-widget-sites"
                                    v-model="sitesText"
                                    rows="4"
                                    dir="ltr"
                                    class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border py-2 ps-9 pe-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none"
                                    :placeholder="t('agents.setup.behavior.allowed_sites_placeholder')"
                                />
                            </div>
                            <p class="text-muted-foreground text-xs">{{ t('agents.setup.behavior.allowed_sites_help') }}</p>
                            <div class="flex justify-end">
                                <Button type="button" size="sm" class="gap-2" :disabled="sitesForm.processing" @click="saveSites">
                                    <Save class="size-4" /> {{ t('agents.deploy.save') }}
                                </Button>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Button type="button" variant="ghost" size="sm" class="text-muted-foreground gap-2" @click="showApiDocsDialog = true">
                                <Terminal class="size-4" /> {{ t('agents.deploy.api_docs') }}
                            </Button>
                            <Button as-child variant="ghost" size="sm" class="text-muted-foreground gap-2">
                                <Link :href="setupUrl"><Settings2 class="size-4" /> {{ t('agents.deploy.customize') }}</Link>
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- ── Hosted Chat ── -->
                <section class="bg-card rounded-xl border">
                    <div class="flex items-start gap-4 border-b p-5">
                        <div class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg">
                            <Globe class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">{{ t('agents.deploy.chat.title') }}</h2>
                            <p class="text-muted-foreground mt-0.5 text-sm">{{ t('agents.deploy.chat.description') }}</p>
                        </div>
                        <div class="ms-auto flex shrink-0 items-center gap-2">
                            <span class="text-muted-foreground text-xs">{{
                                surfaceEnabled.hosted_chat ? t('agents.deploy.enabled') : t('agents.deploy.disabled')
                            }}</span>
                            <Switch
                                :model-value="surfaceEnabled.hosted_chat"
                                :disabled="savingSurfaces"
                                :aria-label="t('agents.deploy.toggle_surface')"
                                @update:model-value="(v) => toggleSurface('hosted_chat', v)"
                            />
                        </div>
                    </div>
                    <div class="space-y-5 p-5" :class="{ 'pointer-events-none opacity-50': !surfaceEnabled.hosted_chat }">
                        <!-- Share URL -->
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">{{ t('agents.deploy.chat.url_label') }}</Label>
                            <div class="bg-muted flex items-center justify-between gap-3 rounded-lg p-3">
                                <code class="text-foreground truncate text-sm" dir="ltr">{{ shareUrl }}</code>
                                <div class="flex shrink-0 items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" class="gap-2" @click="copy(shareUrl, 'url')">
                                        <component :is="copiedKey === 'url' ? Check : Copy" class="size-4" />
                                        {{ copiedKey === 'url' ? t('agents.deploy.copied') : t('agents.deploy.copy') }}
                                    </Button>
                                    <Button as-child variant="outline" size="sm" class="gap-2">
                                        <a :href="shareUrl" target="_blank" rel="noopener"
                                            ><ExternalLink class="size-4" /> {{ t('agents.deploy.chat.open') }}</a
                                        >
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <!-- Iframe embed -->
                        <div class="space-y-3 border-t pt-4">
                            <Label class="text-sm font-medium">{{ t('agents.deploy.chat.embed_label') }}</Label>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <Label for="deploy-width" class="text-muted-foreground text-xs">{{ t('agents.deploy.chat.width') }}</Label>
                                    <div class="relative">
                                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5">
                                            <MoveHorizontal class="size-3.5" />
                                        </div>
                                        <Input id="deploy-width" v-model="iframeOptions.width" type="text" class="ps-8 text-sm" />
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="deploy-height" class="text-muted-foreground text-xs">{{ t('agents.deploy.chat.height') }}</Label>
                                    <div class="relative">
                                        <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5">
                                            <MoveVertical class="size-3.5" />
                                        </div>
                                        <Input id="deploy-height" v-model="iframeOptions.height" type="text" class="ps-8 text-sm" />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-2">
                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setPreset('full')">{{
                                    t('agents.deploy.chat.preset_full')
                                }}</Button>
                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setPreset('large')">{{
                                    t('agents.deploy.chat.preset_large')
                                }}</Button>
                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setPreset('medium')">{{
                                    t('agents.deploy.chat.preset_medium')
                                }}</Button>
                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setPreset('small')">{{
                                    t('agents.deploy.chat.preset_small')
                                }}</Button>
                            </div>
                            <div class="bg-muted space-y-3 rounded-lg p-4">
                                <div class="flex items-center justify-end">
                                    <Button type="button" variant="outline" size="sm" class="gap-2" @click="copy(iframeCode, 'iframe')">
                                        <component :is="copiedKey === 'iframe' ? Check : Copy" class="size-4" />
                                        {{ copiedKey === 'iframe' ? t('agents.deploy.copied') : t('agents.deploy.copy') }}
                                    </Button>
                                </div>
                                <div class="bg-background rounded border p-3" dir="ltr">
                                    <pre
                                        class="hljs overflow-x-auto bg-transparent p-0 text-xs whitespace-pre-wrap"
                                    ><code class="language-xml" v-html="hlIframe" /></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── Command Bar ── -->
                <section class="bg-card rounded-xl border">
                    <div class="flex items-start gap-4 border-b p-5">
                        <div class="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-lg">
                            <Command class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">{{ t('agents.deploy.bar.title') }}</h2>
                            <p class="text-muted-foreground mt-0.5 text-sm">{{ t('agents.deploy.bar.description') }}</p>
                        </div>
                        <div class="ms-auto flex shrink-0 items-center gap-2">
                            <span class="text-muted-foreground text-xs">{{
                                surfaceEnabled.bar ? t('agents.deploy.enabled') : t('agents.deploy.disabled')
                            }}</span>
                            <Switch
                                :model-value="surfaceEnabled.bar"
                                :disabled="savingSurfaces"
                                :aria-label="t('agents.deploy.toggle_surface')"
                                @update:model-value="(v) => toggleSurface('bar', v)"
                            />
                        </div>
                    </div>
                    <div class="space-y-4 p-5" :class="{ 'pointer-events-none opacity-50': !surfaceEnabled.bar }">
                        <div class="bg-muted space-y-3 rounded-lg p-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-muted-foreground text-sm">{{ t('agents.deploy.install_label') }}</p>
                                <Button type="button" variant="outline" size="sm" class="shrink-0 gap-2" @click="copy(barSnippet, 'bar')">
                                    <component :is="copiedKey === 'bar' ? Check : Copy" class="size-4" />
                                    {{ copiedKey === 'bar' ? t('agents.deploy.copied') : t('agents.deploy.copy') }}
                                </Button>
                            </div>
                            <div class="bg-background rounded border p-3" dir="ltr">
                                <pre class="hljs overflow-x-auto bg-transparent p-0 text-xs"><code class="language-xml" v-html="hlBar" /></pre>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <Button type="button" variant="ghost" size="sm" class="text-muted-foreground gap-2" @click="showApiDocsDialog = true">
                                <Terminal class="size-4" /> {{ t('agents.deploy.api_docs') }}
                            </Button>
                            <Button as-child variant="ghost" size="sm" class="text-muted-foreground gap-2">
                                <Link :href="setupUrl"><Settings2 class="size-4" /> {{ t('agents.deploy.customize') }}</Link>
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- JavaScript API docs — the Widget and Bar both expose window.chatvia.* on the host page. -->
        <Dialog v-model:open="showApiDocsDialog">
            <DialogContent class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Terminal class="size-5" />
                        {{ t('agents.setup.api.title') }}
                    </DialogTitle>
                    <DialogDescription>{{ t('agents.setup.api.description') }}</DialogDescription>
                </DialogHeader>

                <div class="flex-1 space-y-6 overflow-y-auto pe-2">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="size-4 text-amber-500" />
                            <h5 class="text-sm font-medium">{{ t('agents.setup.api.methods') }}</h5>
                        </div>
                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="text-muted-foreground px-3 py-2 text-start font-medium">{{ t('agents.setup.api.method_col') }}</th>
                                        <th class="text-muted-foreground px-3 py-2 text-start font-medium">
                                            {{ t('agents.setup.api.description_col') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="item in apiMethods" :key="item.method" class="hover:bg-muted/30 transition-colors">
                                        <td class="px-3 py-2">
                                            <code class="bg-muted text-primary rounded px-1.5 py-0.5 font-mono text-xs" dir="ltr">{{
                                                item.method
                                            }}</code>
                                        </td>
                                        <td class="text-muted-foreground px-3 py-2">{{ item.description }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="size-4 text-emerald-500" />
                            <h5 class="text-sm font-medium">{{ t('agents.setup.api.events') }}</h5>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            {{ t('agents.setup.api.events_hint') }}
                            <code class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs" dir="ltr">window.chatvia.on(event, callback)</code>
                        </p>
                        <div class="border-border bg-muted/40 overflow-hidden rounded-lg border" dir="ltr">
                            <div class="border-border bg-muted text-muted-foreground flex items-center justify-between border-b px-4 py-2">
                                <span class="text-xs font-medium">{{ t('agents.setup.api.code_language') }}</span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="hover:bg-background h-7 px-2"
                                    @click="copyApiCode(eventsCodeExample)"
                                >
                                    <Copy class="me-1.5 size-3.5" />
                                    <span class="text-xs">{{ t('agents.deploy.copy') }}</span>
                                </Button>
                            </div>
                            <pre
                                class="hljs overflow-x-auto bg-transparent p-4 text-sm leading-relaxed"
                            ><code class="language-javascript font-mono" v-html="highlightedEventsExample" /></pre>
                        </div>
                    </div>

                    <Alert class="bg-primary/5 border-primary/20">
                        <Info class="text-primary size-4" />
                        <AlertDescription class="text-sm">
                            <strong>{{ t('agents.setup.api.quick_example') }}</strong> {{ t('agents.setup.api.quick_example_text') }}
                            <code class="bg-muted mt-2 block rounded px-2 py-1 font-mono text-xs" dir="ltr">
                                document.getElementById('help-btn').onclick = () =&gt; window.chatvia.open();
                            </code>
                        </AlertDescription>
                    </Alert>
                </div>

                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showApiDocsDialog = false">{{ t('agents.setup.api.close') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
