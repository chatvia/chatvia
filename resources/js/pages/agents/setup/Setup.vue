<script lang="ts" setup>
import AgentPersonaEditor from '@/components/agents/AgentPersonaEditor.vue';
import BrandingSection from '@/components/agents/sections/BrandingSection.vue';
import ModelsSection from '@/components/agents/sections/ModelsSection.vue';
import StartersSection from '@/components/agents/sections/StartersSection.vue';
import TextSection from '@/components/agents/sections/TextSection.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import hljs from 'highlight.js/lib/core';
import javascript from 'highlight.js/lib/languages/javascript';
import xml from 'highlight.js/lib/languages/xml';
import {
    Bot,
    Check,
    CircleHelp,
    Clock,
    Code,
    Copy,
    ExternalLink,
    Eye,
    Globe,
    Image as ImageIcon,
    Info,
    Keyboard,
    Languages,
    LayoutGrid,
    MapPin,
    Maximize2,
    MessageSquare,
    Monitor,
    MoveHorizontal,
    MoveVertical,
    Palette,
    RotateCcw,
    Save,
    Search,
    Settings,
    Shield,
    SlidersHorizontal,
    Smartphone,
    // Sparkles — only used by the temporarily-hidden Model tab (see menuItems); restore alongside it.
    // Sparkles,
    Tablet,
    Terminal,
    Type,
    Zap,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

hljs.registerLanguage('javascript', javascript);
hljs.registerLanguage('xml', xml);

type Llm = Domain.Llm.Data.LlmModelData;
type Surface = 'hosted_chat' | 'widget' | 'bar';
type Device = 'desktop' | 'tablet' | 'mobile';

interface PromptOption {
    id: string;
    name: string;
    prompt: string;
}

interface Props {
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData & { id?: number | string };
    authToken: string | null;
    starters: { text: string; icon: string | null }[];
    knowledgeQuestions: string[];
    prompts: PromptOption[];
    hasPermission: boolean;
    canUseHandoff: boolean;

    appearanceSettings: Record<string, unknown>;
    defaultAppearance: Record<string, unknown>;
    widgetSettings: Record<string, unknown>;
    defaultWidget: Record<string, unknown>;
    barSettings: Record<string, unknown>;
    defaultBar: Record<string, unknown>;
    embedSettings: Record<string, unknown>;
    defaultEmbed: Record<string, unknown>;

    analyticsSettings: Record<string, unknown>;
    defaultAnalytics: Record<string, unknown>;

    catalog: Llm[];
    selectedModel: string | null;
    llmSettings: { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };
    defaultLlmSettings: { temperature: number; top_k: number; top_p: number; presence_penalty: number; frequency_penalty: number };

    embedScriptUrl: string;
    availableLanguages: Record<string, string>;

    domain: {
        value: string | null;
        cname_target: string;
        quota: { total: number; used: number; available: number };
        has_quota: boolean;
    };
}

const props = defineProps<Props>();

const str = (v: unknown, fallback = ''): string => (typeof v === 'string' ? v : fallback);
const num = (v: unknown, fallback = 0): number => (typeof v === 'number' ? v : fallback);
const bool = (v: unknown, fallback = false): boolean => (typeof v === 'boolean' ? v : fallback);
const arr = <T,>(v: unknown, fallback: T[] = []): T[] => (Array.isArray(v) ? (v as T[]) : fallback);

const a = props.appearanceSettings;
const ad = props.defaultAppearance;
const w = props.widgetSettings;
const wd = props.defaultWidget;
const b = props.barSettings;
const bd = props.defaultBar;
const e = props.embedSettings;
const ed = props.defaultEmbed;
const anl = props.analyticsSettings;
const anlDefault = props.defaultAnalytics;

const canEdit = computed(() => props.hasPermission);
const locked = computed(() => !canEdit.value);

// ── Preview-tab state (Hosted Chat / Widget / Bar) — purely UI; the same
// content settings (Info/Persona/Starters/Model/Branding) save once and apply
// across every surface. Behavior + Embed swap their fields per active tab.
const previewSurface = ref<Surface>('hosted_chat');
const device = ref<Device>('desktop');

const surfaceTabs: { value: Surface; label: string; icon: typeof MessageSquare }[] = [
    { value: 'hosted_chat', label: 'Chat', icon: MessageSquare },
    { value: 'widget', label: 'Widget', icon: Globe },
    { value: 'bar', label: 'Bar', icon: Search },
];

// ── Left sidebar
type MenuValue = 'info' | 'persona' | 'starters' | 'model' | 'branding' | 'behavior' | 'settings' | 'embed' | 'domain';
const activeTab = ref<MenuValue>('info');

const menuItems = computed(() => {
    const items: { value: MenuValue; label: string; icon: typeof MessageSquare }[] = [
        { value: 'info', label: 'Info', icon: Type },
        { value: 'persona', label: 'Persona', icon: Bot },
        { value: 'starters', label: 'Starters', icon: MessageSquare },
        // Model tab temporarily hidden — the `model` content block and all its
        // save logic stay intact below; uncomment this line to restore the tab.
        // { value: 'model', label: 'Model', icon: Sparkles },
        { value: 'branding', label: 'Branding', icon: ImageIcon },
        { value: 'behavior', label: 'Behavior', icon: Settings },
        { value: 'settings', label: 'Settings', icon: SlidersHorizontal },
        { value: 'embed', label: 'Embed', icon: Code },
    ];

    // Domain is a Hosted-Chat-only concern: the custom hostname points at
    // the agent's hosted chat URL, so it doesn't apply to Widget/Bar.
    if (previewSurface.value === 'hosted_chat') {
        items.push({ value: 'domain', label: 'Domain', icon: Globe });
    }

    return items;
});

// If the user switches surfaces while the Domain tab is active, fall back
// to Info so the page doesn't render a hidden tab.
watch(previewSurface, (next) => {
    if (next !== 'hosted_chat' && activeTab.value === 'domain') {
        activeTab.value = 'info';
    }
});

// ── Info (text fields) → appearance group
const textForm = useForm({
    welcome_message: str(a.welcome_message, str(ad.welcome_message)),
    chat_window_title: str(a.chat_window_title, str(ad.chat_window_title)),
    composer_placeholder: str(a.composer_placeholder, str(ad.composer_placeholder)),
});
const isSavingText = ref(false);
const saveText = () => {
    isSavingText.value = true;
    textForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Text saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save text'),
        onFinish: () => (isSavingText.value = false),
    });
};

// ── Settings › Composer affordances — default on. Owners can turn either off
// when the agent shouldn't accept file uploads / shouldn't expose voice
// recording. Same `appearance` group as the text fields, saved on its own.
const composerForm = useForm({
    allow_file_uploads: bool(a.allow_file_uploads, bool(ad.allow_file_uploads, true)),
    allow_voice_input: bool(a.allow_voice_input, bool(ad.allow_voice_input, true)),
});
const isSavingComposer = ref(false);
const saveComposer = () => {
    isSavingComposer.value = true;
    composerForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Composer options saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save composer options'),
        onFinish: () => (isSavingComposer.value = false),
    });
};

// ── Settings › Chat log — master switch for persisting conversations. Off
// stops StoreMessageJob, so nothing is logged (and the analytics below get no
// data to work with). → analytics group.
const chatlogForm = useForm({
    chatlog: bool(anl.chatlog, bool(anlDefault.chatlog, true)),
});
const isSavingChatlog = ref(false);

// ── Settings › Analytics — agent-wide on/off switches for the optional
// conversation insights. Chat log is their master switch: with no stored
// messages there's nothing to analyze, so the dimensions initialise off
// whenever the chat log is off, regardless of their saved value. → analytics group.
const analyticsForm = useForm({
    sentiment: chatlogForm.chatlog && bool(anl.sentiment, bool(anlDefault.sentiment, true)),
    tone: chatlogForm.chatlog && bool(anl.tone, bool(anlDefault.tone, true)),
    topics: chatlogForm.chatlog && bool(anl.topics, bool(anlDefault.topics, true)),
});
const isSavingAnalytics = ref(false);

// Flip every analytics dimension off in the form state — used whenever the chat
// log is off so the toggles drop immediately and a save persists them off.
const disableAllAnalytics = () => {
    analyticsForm.sentiment = false;
    analyticsForm.tone = false;
    analyticsForm.topics = false;
};

// Switching the chat log off cascades to the analytics toggles right away.
watch(
    () => chatlogForm.chatlog,
    (on) => {
        if (!on) {
            disableAllAnalytics();
        }
    },
);

const saveChatlog = () => {
    // When logging is off the analytics can't run — persist them off in the same
    // write so storage matches the UI.
    if (!chatlogForm.chatlog) {
        disableAllAnalytics();
    }
    isSavingChatlog.value = true;
    chatlogForm
        .transform((data) => (data.chatlog ? data : { chatlog: false, sentiment: false, tone: false, topics: false }))
        .post(route('agents.settings.update', { agent: props.agent.external_id, group: 'analytics' }), {
            preserveScroll: true,
            onSuccess: () => toast.success('Chat log settings saved'),
            onError: () => toast.error('Could not save chat log settings'),
            onFinish: () => (isSavingChatlog.value = false),
        });
};

const saveAnalytics = () => {
    // The dimensions can only be on when the chat log is on.
    if (!chatlogForm.chatlog) {
        disableAllAnalytics();
    }
    isSavingAnalytics.value = true;
    analyticsForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'analytics' }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Analytics settings saved'),
        onError: () => toast.error('Could not save analytics settings'),
        onFinish: () => (isSavingAnalytics.value = false),
    });
};

// ── Persona — system instructions / saved library prompt. AgentPersonaEditor
// is a self-contained widget that saves via agents.update on its own. Name +
// sub_domain stay editable via the agent's Rename dialog at the top-level.

// ── Starters → agents.starters.update (agent-level)
const starters = ref<{ text: string; icon: string | null }[]>([...props.starters]);
const isSavingStarters = ref(false);
const saveStarters = async () => {
    isSavingStarters.value = true;
    // Drop blank/whitespace-only lines so empty fields never persist as starters.
    const cleaned = starters.value.map((s) => ({ text: s.text.trim(), icon: s.icon })).filter((s) => s.text);
    try {
        await axios.put(route('agents.starters.update', { agent: props.agent.external_id }), { starters: cleaned });
        starters.value = cleaned;
        toast.success('Starters saved');
        bumpPreview();
    } catch {
        toast.error('Could not save starters');
    } finally {
        isSavingStarters.value = false;
    }
};

// ── Single model + hyperparams → agents.llm.update
const modelForm = useForm({
    model_external_name: props.selectedModel ?? '',
    temperature: props.llmSettings.temperature,
    top_k: props.llmSettings.top_k,
    top_p: props.llmSettings.top_p,
    presence_penalty: props.llmSettings.presence_penalty,
    frequency_penalty: props.llmSettings.frequency_penalty,
});
const isSavingModel = ref(false);

// ModelsSection takes `models: string[]` (single-mode still uses an array of
// one) — adapt to/from our flat modelForm.model_external_name string.
const selectedModelsList = computed({
    get: () => (modelForm.model_external_name ? [modelForm.model_external_name] : []),
    set: (value: string[]) => {
        modelForm.model_external_name = value[0] ?? '';
    },
});

const saveModel = () => {
    if (!modelForm.model_external_name) {
        toast.error('Pick a model first');
        return;
    }
    isSavingModel.value = true;
    modelForm.patch(route('agents.llm.update', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Model saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save model'),
        onFinish: () => (isSavingModel.value = false),
    });
};

// ── Domain (Hosted Chat only) — custom hostname pointed at the agent via CNAME.
const domainForm = useForm({
    domain: props.domain.value ?? '',
});
const isSavingDomain = ref(false);
const isCnameCopied = ref(false);
const saveDomain = () => {
    isSavingDomain.value = true;
    domainForm.patch(route('agents.domain.update', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => toast.success(domainForm.domain ? 'Custom domain saved' : 'Custom domain removed'),
        onError: () => toast.error(domainForm.errors.domain ?? 'Could not save domain'),
        onFinish: () => (isSavingDomain.value = false),
    });
};
const clearDomain = () => {
    domainForm.domain = '';
    saveDomain();
};
const copyCnameTarget = async () => {
    try {
        await navigator.clipboard.writeText(props.domain.cname_target);
        isCnameCopied.value = true;
        setTimeout(() => (isCnameCopied.value = false), 2000);
    } catch {
        toast.error('Could not copy to clipboard');
    }
};

// ── Branding (colors + avatar + logo) → appearance group
const brandingForm = useForm({
    color_theme: str(a.color_theme, str(ad.color_theme, 'black')),
    color_shade: num(a.color_shade, num(ad.color_shade, 700)),
});
const avatarPreview = ref<string | null>(str(a.avatar) || null);
const logoPreview = ref<string | null>(str(a.logo) || null);
const isSavingBranding = ref(false);
const saveBranding = () => {
    isSavingBranding.value = true;
    brandingForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Colors saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save colors'),
        onFinish: () => (isSavingBranding.value = false),
    });
};
const uploadImage = (key: 'avatar' | 'logo', file: File) => {
    const upload = useForm({ [key]: file });
    upload.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success(`${key === 'avatar' ? 'Avatar' : 'Logo'} updated`);
            router.reload({ only: ['appearanceSettings'] });
            bumpPreview();
        },
        onError: () => toast.error(`Could not upload ${key}`),
    });
};
const removeImage = async (key: 'avatar' | 'logo') => {
    try {
        await axios.delete(route('agents.settings.delete', { agent: props.agent.external_id, group: 'appearance' }), {
            data: { key },
        });
        if (key === 'avatar') avatarPreview.value = null;
        else logoPreview.value = null;
        toast.success(`${key === 'avatar' ? 'Avatar' : 'Logo'} removed`);
        bumpPreview();
    } catch {
        toast.error(`Could not remove ${key}`);
    }
};

// ── Behavior — per-surface knobs that swap with `previewSurface`.

// Hosted Chat behavior (iframe options) → embed group
const hostedBehaviorForm = useForm({
    remove_sidebar: bool(e.remove_sidebar, bool(ed.remove_sidebar)),
    hide_sidebar_on_page_load: bool(e.hide_sidebar_on_page_load, bool(ed.hide_sidebar_on_page_load)),
    enable_theme_switcher: bool(e.enable_theme_switcher, bool(ed.enable_theme_switcher, true)),
    default_theme: str(e.default_theme, str(ed.default_theme, 'auto')),
    enable_chat_expand: bool(e.enable_chat_expand, bool(ed.enable_chat_expand, true)),
    language: str(e.language, str(ed.language, 'auto')),
});
const isSavingHostedBehavior = ref(false);
const saveHostedBehavior = () => {
    isSavingHostedBehavior.value = true;
    hostedBehaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'embed' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Hosted Chat behaviour saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save behaviour'),
        onFinish: () => (isSavingHostedBehavior.value = false),
    });
};

// Widget behavior → widget group (position / window_size / show_on_load /
// language / theme / sites). Sites allowlist folds in here; bubbles is a
// separate form edited on the Starters tab.
const widgetBehaviorForm = useForm({
    position: str(w.position, str(wd.position, 'bottom-right')),
    window_size: str(w.window_size, str(wd.window_size, 'large')),
    theme: str(w.theme, str(wd.theme, 'auto')),
    show_on_load: bool(w.show_on_load, bool(wd.show_on_load)),
    show_on_load_delay: num(w.show_on_load_delay, num(wd.show_on_load_delay, 500)),
    language: str(w.language, str(wd.language, 'auto')),
    sites: arr<string>(w.sites, arr<string>(wd.sites)),
    handoff_enabled: bool(w.handoff_enabled, bool(wd.handoff_enabled, true)),
});

// ── Chat Bubbles — quick-message prompts shown next to the widget bubble.
// Lives in the widget settings group but edited on the Starters tab since
// it's conceptually a sibling to conversation starters.
const bubblesForm = useForm({ bubbles: arr<string>(w.bubbles, arr<string>(wd.bubbles)) });
const isSavingBubbles = ref(false);
const saveBubbles = () => {
    isSavingBubbles.value = true;
    bubblesForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Bubbles saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save bubbles'),
        onFinish: () => (isSavingBubbles.value = false),
    });
};
const isSavingWidgetBehavior = ref(false);
const saveWidgetBehavior = () => {
    isSavingWidgetBehavior.value = true;
    widgetBehaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Widget behaviour saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save behaviour'),
        onFinish: () => (isSavingWidgetBehavior.value = false),
    });
};
const sitesText = computed({
    get: () => widgetBehaviorForm.sites.join('\n'),
    set: (value: string) => {
        widgetBehaviorForm.sites = value
            .split(/\n+/)
            .map((s) => s.trim())
            .filter(Boolean);
    },
});

// Bar behavior → bar group (keyboard trigger + placement)
const barBehaviorForm = useForm({
    trigger: str(b.trigger, str(bd.trigger, 'cmd+k')),
    placement: str(b.placement, str(bd.placement, 'top')),
});
const isSavingBarBehavior = ref(false);
const saveBarBehavior = () => {
    isSavingBarBehavior.value = true;
    barBehaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'bar' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Bar behaviour saved');
            bumpPreview();
        },
        onError: () => toast.error('Could not save behaviour'),
        onFinish: () => (isSavingBarBehavior.value = false),
    });
};

// Platform modifier — Cmd (⌘) on Mac, Ctrl everywhere else. The stored
// `cmd+k` etc. values are canonical; the widget translates them per-visitor
// (see src/keyboardTrigger.js in the widget repo), and the owner here sees
// the label that matches the keyboard they'll actually press on.
const isMacPlatform = typeof navigator !== 'undefined' && navigator.platform.toUpperCase().includes('MAC');
const modLabel = isMacPlatform ? '⌘ + ' : 'Ctrl + ';
const triggerOptions = [
    { value: 'cmd+k', label: `${modLabel}K` },
    { value: 'cmd+j', label: `${modLabel}J` },
    { value: 'cmd+/', label: `${modLabel}/` },
    { value: '/', label: '/' },
    { value: 'shift+/', label: '?' },
];
const placementOptions = [
    { value: 'top', label: 'Top center' },
    { value: 'top-right', label: 'Top right' },
    { value: 'bottom', label: 'Bottom center' },
    { value: 'bottom-right', label: 'Bottom right' },
];
const positionOptions = [
    { value: 'bottom-right', label: 'Bottom right' },
    { value: 'bottom-left', label: 'Bottom left' },
    { value: 'top-right', label: 'Top right' },
    { value: 'top-left', label: 'Top left' },
];
const windowSizeOptions = [
    { value: 'small', label: 'Small' },
    { value: 'medium', label: 'Medium' },
    { value: 'large', label: 'Large' },
];
const themeOptions = [
    { value: 'auto', label: 'Auto' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
];

// ── Embed snippet — also swaps with `previewSurface`.
// The preview iframe needs the auth token (dashboard user), but the Share
// section in Embed should show the clean public URL.
const hostedChatUrl = computed(() => {
    const base = props.agent.chat_url;
    if (!props.authToken) return base;
    return `${base}${base.includes('?') ? '&' : '?'}auth_token=${props.authToken}`;
});
const shareUrl = computed(() => props.agent.permanent_url || props.agent.url);

// Hosted Chat iframe embed — width/height/presets configurator. The generated
// `<iframe>` HTML is what an operator drops into their own page.
const iframeOptions = reactive({
    width: '100%',
    height: '600px',
    title: `Chat with ${props.agent.name}`,
});
const setIframePreset = (preset: 'full' | 'large' | 'medium' | 'small'): void => {
    if (preset === 'full') {
        iframeOptions.width = '100%';
        iframeOptions.height = '100vh';
    } else if (preset === 'large') {
        iframeOptions.width = '800px';
        iframeOptions.height = '600px';
    } else if (preset === 'medium') {
        iframeOptions.width = '600px';
        iframeOptions.height = '500px';
    } else {
        iframeOptions.width = '400px';
        iframeOptions.height = '400px';
    }
};
const iframeEmbedCode = computed(
    () =>
        `<iframe src="${props.agent.chat_embed_url}" width="${iframeOptions.width}" height="${iframeOptions.height}" title="${iframeOptions.title}" frameborder="0" allowfullscreen loading="lazy"></iframe>`,
);
const highlightedIframe = computed(() => hljs.highlight(iframeEmbedCode.value, { language: 'xml', ignoreIllegals: true }).value);
const iframeCopied = ref(false);
const copyIframe = async () => {
    try {
        await navigator.clipboard.writeText(iframeEmbedCode.value);
        iframeCopied.value = true;
        toast.success('Embed code copied to clipboard!');
        setTimeout(() => (iframeCopied.value = false), 2000);
    } catch {
        toast.error('Copy failed');
    }
};

// Private agents need a server-minted JWT — append a `data-auth` placeholder
// to the snippet so customers know to fill it in on their backend, and show
// the protected-widget alert below.
const isProtected = computed(() => !props.agent.is_public);

const widgetSnippet = computed(() => {
    const lines = [
        '<script id="chatbot-embed-script"',
        `    src="${props.embedScriptUrl}"`,
        `    data-agent-id="${props.agent.external_id}"`,
        `    data-language="${widgetBehaviorForm.language}"`,
        `    data-theme="${widgetBehaviorForm.theme}"`,
    ];
    if (isProtected.value) lines.push('    data-auth="YOUR_SIGNED_TOKEN"');
    lines.push('    defer><\/script>');
    return lines.join('\n');
});

const barSnippet = computed(() => {
    const lines = [
        '<script id="chatbot-embed-script"',
        `    src="${props.embedScriptUrl}"`,
        `    data-agent-id="${props.agent.external_id}"`,
        '    data-mode="bar"',
        `    data-trigger="${barBehaviorForm.trigger}"`,
        `    data-placement="${barBehaviorForm.placement}"`,
    ];
    if (isProtected.value) lines.push('    data-auth="YOUR_SIGNED_TOKEN"');
    lines.push('    defer><\/script>');
    return lines.join('\n');
});
const activeSnippet = computed(() => {
    if (previewSurface.value === 'widget') return widgetSnippet.value;
    if (previewSurface.value === 'bar') return barSnippet.value;
    return shareUrl.value;
});

// Syntax-highlighted snippet for the Widget/Bar Embed code block. The active
// snippet is a `<script>` tag, so the xml grammar gives the right colors for
// tag names, attribute names, and quoted values.
const highlightedSnippet = computed(() => hljs.highlight(activeSnippet.value, { language: 'xml', ignoreIllegals: true }).value);
const highlightedEventsExample = computed(() => hljs.highlight(eventsCodeExample, { language: 'javascript', ignoreIllegals: true }).value);
const copied = ref(false);
const copyActive = async () => {
    try {
        await navigator.clipboard.writeText(activeSnippet.value);
        copied.value = true;
        toast.success('Copied to clipboard');
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        toast.error('Copy failed');
    }
};

// ── Live Preview iframe — surface-aware.
const previewUrl = computed(() => {
    if (previewSurface.value === 'hosted_chat') return hostedChatUrl.value;
    if (previewSurface.value === 'widget') {
        return `${route('agents.widget.preview', { agent: props.agent.external_id })}?full-preview=true`;
    }
    return route('agents.bar.preview', { agent: props.agent.external_id });
});
const frameClass = computed(() => {
    if (device.value === 'tablet') {
        return 'h-[calc(100%-2rem)] max-h-[900px] w-[768px] rounded-xl border shadow-lg overflow-hidden bg-white';
    }
    if (device.value === 'mobile') {
        return 'h-[calc(100%-2rem)] max-h-[800px] w-[414px] rounded-xl border shadow-lg overflow-hidden bg-white';
    }
    return 'h-full w-full';
});
const openFullPreview = () => {
    if (previewUrl.value) window.open(previewUrl.value, '_blank');
};

// Resizable preview pane — disable iframe pointer events while the user is
// dragging the handle so the mouse-up lands on the splitter instead of
// inside the iframe.
const isPreviewResizing = ref(false);

// Bumped after every successful save so the preview iframe re-mounts and
// picks up the new settings. Threaded into the iframe :key. Also bumped by
// the manual Refresh button in the preview header.
const previewKey = ref(0);
const bumpPreview = () => {
    previewKey.value += 1;
};
const refreshPreview = () => bumpPreview();

// Copy the agent's external id (the public identifier used in embed snippets,
// routes, and the API) straight from the preview toolbar.
const isExternalIdCopied = ref(false);
const copyExternalId = async () => {
    try {
        await navigator.clipboard.writeText(props.agent.external_id);
        isExternalIdCopied.value = true;
        toast.success('Agent ID copied to clipboard');
        setTimeout(() => (isExternalIdCopied.value = false), 2000);
    } catch {
        toast.error('Could not copy to clipboard');
    }
};

// ── JavaScript API reference — shown for Widget + Bar (both surfaces install
// via the same `window.chatvia` script global). Hosted Chat is a full-page
// URL, no JS API; the template branch for hosted_chat doesn't render the
// trigger button.
const showApiDocsDialog = ref(false);

const apiMethods = [
    { method: 'window.chatvia.open()', description: 'Opens the chat window' },
    { method: 'window.chatvia.close()', description: 'Closes the chat window' },
    { method: 'window.chatvia.toggle()', description: 'Toggles open/close state' },
    { method: 'window.chatvia.isOpen()', description: 'Returns boolean if open' },
    { method: 'window.chatvia.send(msg)', description: 'Sends a message programmatically' },
    { method: 'window.chatvia.newChat()', description: 'Starts a new chat thread' },
    { method: 'window.chatvia.destroy()', description: 'Destroys widget & cleans up' },
    { method: 'window.chatvia.on(event, fn)', description: 'Subscribe to events (open, close, ready)' },
    { method: 'window.chatvia.off(event, fn)', description: 'Unsubscribe from events' },
];

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

const copyApiCode = async (code: string) => {
    try {
        await navigator.clipboard.writeText(code);
        toast.success('Code copied to clipboard!');
    } catch {
        toast.error('Failed to copy code. Please try again.');
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Setup" hide-header no-padding full-height>
        <div class="bg-background flex h-full flex-col">
            <!-- Preview-tab bar — local UI state. Setup-style underline nav. -->
            <div class="bg-background border-border flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b pr-4 lg:pr-6">
                <nav class="flex items-center gap-1 px-4">
                    <button
                        v-for="tab in surfaceTabs"
                        :key="tab.value"
                        type="button"
                        @click="previewSurface = tab.value"
                        class="relative flex items-center gap-2 px-4 py-3 text-sm transition-colors"
                        :class="[previewSurface === tab.value ? 'text-foreground font-medium' : 'text-muted-foreground hover:text-foreground']"
                    >
                        <component :is="tab.icon" class="h-4 w-4" />
                        <span>{{ tab.label }}</span>
                        <span v-if="previewSurface === tab.value" class="bg-foreground absolute right-0 -bottom-px left-0 h-0.5"></span>
                    </button>
                </nav>
            </div>

            <!-- Layout: (left sidebar + middle content) | drag-resizable | right live preview -->
            <ResizablePanelGroup direction="horizontal" auto-save-id="agent-setup-preview" class="min-w-0 flex-1">
                <ResizablePanel :default-size="62" :min-size="35">
                    <div class="flex h-full min-h-0">
                        <!-- Left sidebar -->
                        <div class="bg-muted/30 flex h-full w-[180px] flex-shrink-0 flex-col border-r">
                            <nav class="flex flex-col gap-0.5 p-2">
                                <button
                                    v-for="item in menuItems"
                                    :key="item.value"
                                    @click="activeTab = item.value"
                                    class="flex h-9 w-full cursor-pointer items-center gap-2.5 rounded-md px-3 text-sm transition-colors"
                                    :class="[
                                        activeTab === item.value
                                            ? 'bg-background text-foreground font-medium shadow-sm'
                                            : 'text-muted-foreground hover:bg-background/50 hover:text-foreground',
                                    ]"
                                >
                                    <component :is="item.icon" class="h-4 w-4 flex-shrink-0" />
                                    <span>{{ item.label }}</span>
                                </button>
                            </nav>
                        </div>

                        <!-- Middle column - content -->
                        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                            <Alert v-if="!canEdit" variant="destructive" class="rounded-none border-x-0 border-t-0">
                                <Shield class="h-4 w-4" />
                                <AlertTitle>View Only</AlertTitle>
                                <AlertDescription>You don't have permission to edit Setup.</AlertDescription>
                            </Alert>

                            <div class="flex-1 overflow-y-auto" :class="{ 'pointer-events-none opacity-60 select-none': !canEdit }">
                                <!-- Info -->
                                <div v-if="activeTab === 'info'" class="space-y-6 p-6">
                                    <form @submit.prevent="saveText" class="space-y-6">
                                        <TextSection
                                            v-model:welcome-message="textForm.welcome_message"
                                            v-model:chat-window-title="textForm.chat_window_title"
                                            v-model:composer-placeholder="textForm.composer_placeholder"
                                            :errors="textForm.errors as Record<string, string>"
                                            :disabled="locked"
                                        />

                                        <div v-if="!locked" class="border-t pt-4">
                                            <Button type="submit" :disabled="!canEdit || isSavingText" size="sm">
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingText ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Settings — agent-wide options that aren't tied to a single
                                     surface: composer affordances + which analytics this agent collects. -->
                                <div v-if="activeTab === 'settings'" class="max-w-3xl space-y-8 p-6">
                                    <!-- Composer options — show/hide the paperclip + mic buttons in
                                         the chat composer. Same appearance group as the text fields. -->
                                    <form @submit.prevent="saveComposer" class="space-y-3">
                                        <div>
                                            <h3 class="text-sm font-medium">Composer options</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                Hide the file-upload or voice-input button if your agent shouldn't accept those inputs.
                                            </p>
                                        </div>
                                        <div class="divide-y rounded-md border">
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="allow-file-uploads" class="cursor-pointer text-sm font-medium"
                                                            >Allow file uploads</Label
                                                        >
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    aria-label="How are file uploads charged?"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">File uploads</p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    Visitors can attach a PDF and ask questions about it.
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    Each page counts as one message, on top of the normal message for the question. So
                                                                    a 10-page PDF costs 11 messages per question (1 + 10 pages). This is charged every
                                                                    time a question is asked while the PDF is in the chat.
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        Show the paperclip button so visitors can attach PDFs.
                                                    </p>
                                                </div>
                                                <Switch id="allow-file-uploads" v-model="composerForm.allow_file_uploads" :disabled="locked" />
                                            </div>
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <Label for="allow-voice-input" class="cursor-pointer text-sm font-medium"
                                                        >Allow voice input</Label
                                                    >
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        Show the microphone button so visitors can dictate messages.
                                                    </p>
                                                </div>
                                                <Switch id="allow-voice-input" v-model="composerForm.allow_voice_input" :disabled="locked" />
                                            </div>
                                        </div>
                                        <div v-if="!locked">
                                            <Button type="submit" :disabled="!canEdit || isSavingComposer" size="sm">
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingComposer ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </form>

                                    <Separator />

                                    <!-- Chat log — master switch for persisting conversations. Off
                                         stops StoreMessageJob, so nothing is logged. -->
                                    <form @submit.prevent="saveChatlog" class="space-y-3">
                                        <div>
                                            <h3 class="text-sm font-medium">Chat log</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                Store conversations so they show up in Chat Logs and feed analytics.
                                            </p>
                                        </div>
                                        <div class="divide-y rounded-md border">
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="chatlog-enabled" class="cursor-pointer text-sm font-medium"
                                                            >Store conversations</Label
                                                        >
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    aria-label="What does storing conversations mean?"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">Store conversations</p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    When on, every chat is saved to your account so it appears in Chat Logs and feeds
                                                                    the analytics below.
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    When off, nothing is saved on our side. The logged-in chatter can still see their
                                                                    own messages in the chat window during their session, but those messages aren't
                                                                    persisted — only that chatter can see them, and they're gone once the session
                                                                    ends.
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        When off, messages aren't saved — so the analytics below can't collect data either.
                                                    </p>
                                                </div>
                                                <Switch id="chatlog-enabled" v-model="chatlogForm.chatlog" :disabled="locked" />
                                            </div>
                                        </div>
                                        <div v-if="!locked">
                                            <Button type="submit" :disabled="!canEdit || isSavingChatlog" size="sm">
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingChatlog ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </form>

                                    <Separator />

                                    <!-- Analytics — per-agent on/off for the optional LLM-powered
                                         conversation insights. Default on; off stops the analysis. -->
                                    <form @submit.prevent="saveAnalytics" class="space-y-3">
                                        <div>
                                            <h3 class="text-sm font-medium">Analytics</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                Choose which conversation insights this agent collects. Turning one off stops the analysis and hides
                                                its dashboard.
                                            </p>
                                        </div>
                                        <div class="divide-y rounded-md border" :class="{ 'opacity-60': !chatlogForm.chatlog }">
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="analytics-sentiment" class="cursor-pointer text-sm font-medium">Sentiment</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    aria-label="What does Sentiment track?"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">Sentiment</p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    After each chat, the model reads the conversation and labels the overall mood as
                                                                    positive, neutral, or negative.
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    The results roll up on the Sentiment dashboard so you can spot unhappy visitors
                                                                    and watch trends over time. Needs Store conversations on — there's nothing to
                                                                    analyze otherwise.
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        Track whether visitors feel positive, neutral or negative.
                                                    </p>
                                                </div>
                                                <Switch
                                                    id="analytics-sentiment"
                                                    v-model="analyticsForm.sentiment"
                                                    :disabled="locked || !chatlogForm.chatlog"
                                                />
                                            </div>
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="analytics-tone" class="cursor-pointer text-sm font-medium">Tone</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    aria-label="What does Tone track?"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">Tone</p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    Tone looks at how visitors express themselves — friendly, frustrated, curious, and
                                                                    more — rather than just positive or negative.
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    It's detected automatically after each chat and feeds the Tone dashboard, so you
                                                                    can see how people tend to approach your agent. Needs Store conversations on.
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        Track the communication style — friendly, frustrated, curious and more.
                                                    </p>
                                                </div>
                                                <Switch id="analytics-tone" v-model="analyticsForm.tone" :disabled="locked || !chatlogForm.chatlog" />
                                            </div>
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="analytics-topics" class="cursor-pointer text-sm font-medium">Topics</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    aria-label="What does Topics track?"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">Topics</p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    Topics scans conversations and groups them by what people ask about, so you don't
                                                                    have to tag chats by hand.
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    New topics are detected automatically and rolled up on the Topics dashboard,
                                                                    showing you the most common themes. Needs Store conversations on.
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        Automatically detect and group what visitors ask about.
                                                    </p>
                                                </div>
                                                <Switch
                                                    id="analytics-topics"
                                                    v-model="analyticsForm.topics"
                                                    :disabled="locked || !chatlogForm.chatlog"
                                                />
                                            </div>
                                        </div>
                                        <div v-if="!locked">
                                            <Button type="submit" :disabled="!canEdit || isSavingAnalytics || !chatlogForm.chatlog" size="sm">
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingAnalytics ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Persona -->
                                <div v-if="activeTab === 'persona'" class="space-y-6 p-6">
                                    <AgentPersonaEditor
                                        :agent-id="agent.external_id"
                                        :initial-instructions="agent.system_instructions"
                                        :initial-prompt-id="agent.prompt_id ?? null"
                                        :locked="locked"
                                        @saved="bumpPreview"
                                    />
                                </div>

                                <!-- Starters -->
                                <div v-if="activeTab === 'starters'" class="space-y-6 p-6">
                                    <StartersSection v-model:starters="starters" :knowledge-questions="knowledgeQuestions" :disabled="locked" />
                                    <div v-if="!locked">
                                        <Button type="button" size="sm" :disabled="!canEdit || isSavingStarters" @click="saveStarters">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSavingStarters ? 'Saving...' : 'Save' }}
                                        </Button>
                                    </div>

                                    <!-- Chat Bubbles — widget surface only. Hosted Chat and Bar don't
                                         render quick-message bubbles next to the launcher, so the field
                                         would just be dead settings noise on those tabs. -->
                                    <template v-if="previewSurface === 'widget'">
                                        <Separator />

                                        <div class="space-y-4">
                                            <div>
                                                <h3 class="text-sm font-medium">Chat Bubbles</h3>
                                                <p class="text-muted-foreground mt-1 text-xs">
                                                    Quick message prompts shown next to the widget bubble. Press Enter to add each one.
                                                </p>
                                            </div>

                                            <TagsInput v-model="bubblesForm.bubbles" :disabled="locked" class="w-full">
                                                <TagsInputItem v-for="(bubble, index) in bubblesForm.bubbles" :key="index" :value="bubble">
                                                    <TagsInputItemText />
                                                    <TagsInputItemDelete />
                                                </TagsInputItem>
                                                <TagsInputInput placeholder="e.g., How can I help you?" />
                                            </TagsInput>
                                            <p v-if="bubblesForm.errors.bubbles" class="text-destructive text-sm">
                                                {{ bubblesForm.errors.bubbles }}
                                            </p>

                                            <div v-if="!locked">
                                                <Button type="button" size="sm" :disabled="!canEdit || isSavingBubbles" @click="saveBubbles">
                                                    <Save class="mr-2 h-4 w-4" />
                                                    {{ isSavingBubbles ? 'Saving...' : 'Save' }}
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Model — single picker + hyperparams -->
                                <div v-if="activeTab === 'model'" class="space-y-6 p-6">
                                    <ModelsSection
                                        v-model:models="selectedModelsList"
                                        :catalog="catalog"
                                        :single="true"
                                        :show-response-settings="false"
                                        :errors="modelForm.errors as Record<string, string>"
                                        :disabled="locked"
                                    />

                                    <div v-if="!locked" class="border-t pt-4">
                                        <Button type="button" size="sm" :disabled="!canEdit || isSavingModel" @click="saveModel">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSavingModel ? 'Saving...' : 'Save' }}
                                        </Button>
                                    </div>
                                </div>

                                <!-- Branding — colors + avatar + logo -->
                                <div v-if="activeTab === 'branding'" class="space-y-6 p-6">
                                    <BrandingSection
                                        v-model:color-theme="brandingForm.color_theme"
                                        v-model:color-shade="brandingForm.color_shade"
                                        :avatar-preview="avatarPreview"
                                        :logo-preview="logoPreview"
                                        :errors="brandingForm.errors as Record<string, string>"
                                        :disabled="locked"
                                        @color-apply="saveBranding"
                                        @avatar-select="(file: File) => uploadImage('avatar', file)"
                                        @avatar-remove="() => removeImage('avatar')"
                                        @logo-select="(file: File) => uploadImage('logo', file)"
                                        @logo-remove="() => removeImage('logo')"
                                    />
                                </div>

                                <!-- Behavior — swaps with previewSurface -->
                                <div v-if="activeTab === 'behavior'" class="space-y-6 p-6">
                                    <!-- Hosted Chat -->
                                    <template v-if="previewSurface === 'hosted_chat'">
                                        <div>
                                            <h3 class="text-sm font-medium">Hosted Chat behaviour</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">Iframe options for the embedded Hosted Chat surface.</p>
                                        </div>
                                        <div class="max-w-3xl space-y-5">
                                            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                                <div class="space-y-1.5">
                                                    <Label for="hosted-default-theme" class="text-sm font-medium">Default theme</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                        >
                                                            <Palette class="h-4 w-4" />
                                                        </div>
                                                        <Select v-model="hostedBehaviorForm.default_theme" :disabled="locked">
                                                            <SelectTrigger id="hosted-default-theme" class="h-10 w-full pl-9">
                                                                <SelectValue />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="t in themeOptions" :key="t.value" :value="t.value">{{
                                                                    t.label
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                                <div class="space-y-1.5">
                                                    <Label for="hosted-language" class="text-sm font-medium">Language</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                        >
                                                            <Languages class="h-4 w-4" />
                                                        </div>
                                                        <Select v-model="hostedBehaviorForm.language" :disabled="locked">
                                                            <SelectTrigger id="hosted-language" class="h-10 w-full pl-9"
                                                                ><SelectValue
                                                            /></SelectTrigger>
                                                            <SelectContent>
                                                                <SelectItem v-for="(label, code) in availableLanguages" :key="code" :value="code">{{
                                                                    label
                                                                }}</SelectItem>
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="divide-y rounded-md border">
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-remove-sidebar" class="cursor-pointer text-sm font-medium"
                                                        >Remove sidebar</Label
                                                    >
                                                    <Switch
                                                        id="hosted-remove-sidebar"
                                                        v-model="hostedBehaviorForm.remove_sidebar"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-hide-sidebar" class="cursor-pointer text-sm font-medium"
                                                        >Hide sidebar on page load</Label
                                                    >
                                                    <Switch
                                                        id="hosted-hide-sidebar"
                                                        v-model="hostedBehaviorForm.hide_sidebar_on_page_load"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-theme-switcher" class="cursor-pointer text-sm font-medium"
                                                        >Enable theme switcher</Label
                                                    >
                                                    <Switch
                                                        id="hosted-theme-switcher"
                                                        v-model="hostedBehaviorForm.enable_theme_switcher"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-chat-expand" class="cursor-pointer text-sm font-medium"
                                                        >Enable chat expand</Label
                                                    >
                                                    <Switch
                                                        id="hosted-chat-expand"
                                                        v-model="hostedBehaviorForm.enable_chat_expand"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!locked" class="border-t pt-4">
                                            <Button
                                                type="button"
                                                size="sm"
                                                :disabled="!canEdit || isSavingHostedBehavior"
                                                @click="saveHostedBehavior"
                                            >
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingHostedBehavior ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </template>

                                    <!-- Widget -->
                                    <template v-else-if="previewSurface === 'widget'">
                                        <div>
                                            <h3 class="text-sm font-medium">Widget behaviour</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                Position, size, theme, and allowlisted sites for the floating widget.
                                            </p>
                                        </div>
                                        <div class="grid max-w-3xl gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="space-y-1.5">
                                                <Label for="widget-position" class="text-sm font-medium">Position</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                    >
                                                        <MapPin class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.position" :disabled="locked">
                                                        <SelectTrigger id="widget-position" class="h-10 w-full pl-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="p in positionOptions" :key="p.value" :value="p.value">{{
                                                                p.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="widget-size" class="text-sm font-medium">Window size</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                    >
                                                        <Maximize2 class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.window_size" :disabled="locked">
                                                        <SelectTrigger id="widget-size" class="h-10 w-full pl-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="s in windowSizeOptions" :key="s.value" :value="s.value">{{
                                                                s.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="widget-theme" class="text-sm font-medium">Theme</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                    >
                                                        <Palette class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.theme" :disabled="locked">
                                                        <SelectTrigger id="widget-theme" class="h-10 w-full pl-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="t in themeOptions" :key="t.value" :value="t.value">{{
                                                                t.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="widget-language" class="text-sm font-medium">Language</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                    >
                                                        <Languages class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.language" :disabled="locked">
                                                        <SelectTrigger id="widget-language" class="h-10 w-full pl-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="(label, code) in availableLanguages" :key="code" :value="code">{{
                                                                label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div
                                                v-if="canUseHandoff"
                                                class="bg-muted/40 flex items-center justify-between gap-3 rounded-md border px-4 py-3 sm:col-span-2"
                                            >
                                                <div>
                                                    <Label for="widget-handoff" class="cursor-pointer text-sm font-medium">Live Chat</Label>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        Let visitors hand off to a human. Works on the website widget only.
                                                    </p>
                                                </div>
                                                <Switch id="widget-handoff" v-model="widgetBehaviorForm.handoff_enabled" :disabled="locked" />
                                            </div>
                                            <div
                                                class="bg-muted/40 flex items-center justify-between gap-3 rounded-md border px-4 py-3 sm:col-span-2"
                                            >
                                                <Label for="widget-show-on-load" class="cursor-pointer text-sm font-medium"
                                                    >Open automatically on page load</Label
                                                >
                                                <Switch id="widget-show-on-load" v-model="widgetBehaviorForm.show_on_load" :disabled="locked" />
                                            </div>
                                            <div v-if="widgetBehaviorForm.show_on_load" class="space-y-1.5 sm:col-span-2">
                                                <Label for="widget-delay" class="text-sm font-medium">Delay (ms)</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                                                    >
                                                        <Clock class="h-4 w-4" />
                                                    </div>
                                                    <Input
                                                        id="widget-delay"
                                                        type="number"
                                                        min="0"
                                                        v-model="widgetBehaviorForm.show_on_load_delay"
                                                        :disabled="locked"
                                                        class="h-10 pl-9"
                                                    />
                                                </div>
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-2">
                                                <Label for="widget-sites" class="text-sm font-medium"
                                                    >Allowed sites <span class="text-muted-foreground font-normal">(one per line)</span></Label
                                                >
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute top-0 left-0 flex items-center pt-2.5 pl-3"
                                                    >
                                                        <Globe class="h-4 w-4" />
                                                    </div>
                                                    <textarea
                                                        id="widget-sites"
                                                        v-model="sitesText"
                                                        rows="4"
                                                        :disabled="locked"
                                                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border py-2 pr-3 pl-9 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                        placeholder="example.com&#10;app.example.com"
                                                    />
                                                </div>
                                                <p class="text-muted-foreground text-xs">Only these origins are allowed to load the widget.</p>
                                            </div>
                                        </div>
                                        <div v-if="!locked" class="border-t pt-4">
                                            <Button
                                                type="button"
                                                size="sm"
                                                :disabled="!canEdit || isSavingWidgetBehavior"
                                                @click="saveWidgetBehavior"
                                            >
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingWidgetBehavior ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </template>

                                    <!-- Bar -->
                                    <template v-else>
                                        <div>
                                            <h3 class="text-sm font-medium">Bar behaviour</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">Keyboard trigger and on-page position of the launcher.</p>
                                        </div>
                                        <div class="grid max-w-3xl gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="space-y-1.5">
                                                <Label for="bar-trigger" class="text-sm font-medium">Keyboard trigger</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                    >
                                                        <Keyboard class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="barBehaviorForm.trigger" :disabled="locked">
                                                        <SelectTrigger id="bar-trigger" class="h-10 w-full pl-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="t in triggerOptions" :key="t.value" :value="t.value">{{
                                                                t.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="bar-placement" class="text-sm font-medium">Placement</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 z-10 flex items-center pl-3"
                                                    >
                                                        <LayoutGrid class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="barBehaviorForm.placement" :disabled="locked">
                                                        <SelectTrigger id="bar-placement" class="h-10 w-full pl-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="p in placementOptions" :key="p.value" :value="p.value">{{
                                                                p.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!locked" class="border-t pt-4">
                                            <Button type="button" size="sm" :disabled="!canEdit || isSavingBarBehavior" @click="saveBarBehavior">
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingBarBehavior ? 'Saving...' : 'Save' }}
                                            </Button>
                                        </div>
                                    </template>
                                </div>

                                <!-- Embed — swaps with previewSurface -->
                                <div v-if="activeTab === 'embed'" class="space-y-4 p-6">
                                    <!-- Hosted Chat — shareable agent URL with a side-by-side copy button. -->
                                    <template v-if="previewSurface === 'hosted_chat'">
                                        <div>
                                            <Label class="text-base">Agent URL</Label>
                                            <p class="text-muted-foreground mt-1 mb-3 text-sm">Share this URL to give others access to your agent</p>
                                        </div>

                                        <div class="bg-muted rounded-lg p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <code class="text-foreground text-sm break-all">{{ shareUrl }}</code>
                                                <Button type="button" variant="outline" size="sm" @click="copyActive" class="shrink-0 gap-2">
                                                    <component :is="copied ? Check : Copy" class="h-4 w-4" />
                                                    {{ copied ? 'Copied' : 'Copy' }}
                                                </Button>
                                            </div>
                                        </div>

                                        <Alert>
                                            <Info class="h-4 w-4" />
                                            <AlertDescription>
                                                Anyone with this URL can access your agent. Make sure to set the appropriate security settings.
                                            </AlertDescription>
                                        </Alert>

                                        <!-- Iframe embed configurator — width/height + presets, generated <iframe>. -->
                                        <div class="space-y-3 border-t pt-4">
                                            <Label class="text-sm font-medium">Embed configuration</Label>
                                            <p class="text-muted-foreground text-xs">
                                                Drop the agent into any page with an
                                                <code class="bg-muted rounded px-1 py-0.5 text-xs">&lt;iframe&gt;</code>.
                                            </p>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="space-y-1.5">
                                                    <Label for="iframe-width" class="text-xs">Width</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5"
                                                        >
                                                            <MoveHorizontal class="h-3.5 w-3.5" />
                                                        </div>
                                                        <Input
                                                            id="iframe-width"
                                                            v-model="iframeOptions.width"
                                                            type="text"
                                                            placeholder="100%"
                                                            class="pl-8 text-sm"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="space-y-1.5">
                                                    <Label for="iframe-height" class="text-xs">Height</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5"
                                                        >
                                                            <MoveVertical class="h-3.5 w-3.5" />
                                                        </div>
                                                        <Input
                                                            id="iframe-height"
                                                            v-model="iframeOptions.height"
                                                            type="text"
                                                            placeholder="600px"
                                                            class="pl-8 text-sm"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-4 gap-2">
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('full')"
                                                    >Full</Button
                                                >
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('large')"
                                                    >Large</Button
                                                >
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('medium')"
                                                    >Medium</Button
                                                >
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('small')"
                                                    >Small</Button
                                                >
                                            </div>
                                        </div>

                                        <div class="bg-muted space-y-3 rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <p class="text-muted-foreground text-sm">HTML Embed Code</p>
                                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="copyIframe">
                                                    <component :is="iframeCopied ? Check : Copy" class="h-4 w-4" />
                                                    {{ iframeCopied ? 'Copied' : 'Copy' }}
                                                </Button>
                                            </div>
                                            <div class="bg-background rounded border p-3">
                                                <pre
                                                    class="hljs overflow-x-auto bg-transparent p-0 text-xs whitespace-pre-wrap"
                                                ><code class="language-xml" v-html="highlightedIframe" /></pre>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Widget / Bar — installation snippet + JS API docs link. -->
                                    <template v-else>
                                        <div>
                                            <h3 class="text-sm font-medium">Installation code</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                Paste this snippet into your website's
                                                <code class="bg-muted rounded px-1 py-0.5 text-xs">&lt;head&gt;</code> section.
                                            </p>
                                        </div>
                                        <div class="bg-muted space-y-3 rounded-lg p-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="copyActive">
                                                    <component :is="copied ? Check : Copy" class="h-4 w-4" />
                                                    {{ copied ? 'Copied' : 'Copy' }}
                                                </Button>
                                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="openFullPreview">
                                                    <Eye class="h-4 w-4" />
                                                    Full Preview
                                                </Button>
                                            </div>
                                            <div class="bg-background rounded border p-3">
                                                <pre
                                                    class="hljs overflow-x-auto bg-transparent p-0 text-xs"
                                                ><code class="language-xml" v-html="highlightedSnippet" /></pre>
                                            </div>
                                        </div>

                                        <Alert v-if="isProtected">
                                            <Shield class="h-4 w-4" />
                                            <AlertTitle>This {{ previewSurface }} is protected</AlertTitle>
                                            <AlertDescription>
                                                Replace
                                                <code class="bg-muted rounded px-1 py-0.5 text-xs">YOUR_SIGNED_TOKEN</code>
                                                with a JWT you sign on your server for each visitor — without a valid token it won't load.
                                                <Link
                                                    :href="route('agents.identity', { agent: agent.external_id })"
                                                    class="text-primary font-medium hover:underline"
                                                >
                                                    Open Security settings
                                                </Link>
                                            </AlertDescription>
                                        </Alert>

                                        <Button type="button" variant="outline" class="w-full justify-start gap-2" @click="showApiDocsDialog = true">
                                            <Terminal class="h-4 w-4" />
                                            JavaScript API Documentation
                                        </Button>
                                    </template>
                                </div>

                                <!-- Domain — Hosted Chat only. Gated by Custom Domain subscription / Pro plan quota. -->
                                <div v-if="activeTab === 'domain'" class="space-y-6 p-6">
                                    <div>
                                        <h3 class="text-sm font-medium">Custom Domain</h3>
                                        <p class="text-muted-foreground mt-1 text-xs">
                                            Serve this agent's Hosted Chat from your own domain instead of
                                            <code class="bg-muted rounded px-1 py-0.5 text-xs">{{ domain.cname_target }}</code
                                            >.
                                        </p>
                                    </div>

                                    <!-- Subscription gate -->
                                    <Alert v-if="!domain.has_quota" class="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                                        <Globe class="h-4 w-4" />
                                        <AlertTitle>Custom domains aren't included in your current plan</AlertTitle>
                                        <AlertDescription>
                                            Upgrade to Pro or subscribe to the Custom Domain add-on from
                                            <Link :href="route('billing.index')" class="text-primary font-medium hover:underline">Billing</Link>
                                            to add your own hostname.
                                        </AlertDescription>
                                    </Alert>

                                    <!-- Quota counter -->
                                    <div v-else class="bg-muted/40 flex items-center justify-between rounded-lg border p-3 text-sm">
                                        <span class="text-muted-foreground">
                                            Using
                                            <span class="text-foreground font-semibold">{{ domain.quota.used + (domain.value ? 1 : 0) }}</span> of
                                            <span class="text-foreground font-semibold">{{ domain.quota.total }}</span> custom domain<span
                                                v-if="domain.quota.total !== 1"
                                                >s</span
                                            >
                                        </span>
                                        <Link
                                            v-if="domain.quota.available === 0 && !domain.value"
                                            :href="route('billing.index')"
                                            class="text-primary text-xs font-medium hover:underline"
                                        >
                                            Add more
                                        </Link>
                                    </div>

                                    <!-- CNAME instructions -->
                                    <div v-if="domain.has_quota" class="space-y-3">
                                        <Label class="text-xs">Step 1 — Add a CNAME record at your DNS provider</Label>
                                        <div class="bg-muted overflow-x-auto rounded-lg border p-4 font-mono text-xs">
                                            <div class="grid grid-cols-[80px_1fr] gap-x-4 gap-y-1">
                                                <span class="text-muted-foreground">Type</span><span class="font-semibold">CNAME</span>
                                                <span class="text-muted-foreground">Name</span><span>your-subdomain</span>
                                                <span class="text-muted-foreground">Value</span>
                                                <span class="flex items-center gap-2">
                                                    <code class="font-semibold">{{ domain.cname_target }}</code>
                                                    <Button type="button" variant="ghost" size="sm" class="h-6 gap-1 px-2" @click="copyCnameTarget">
                                                        <component :is="isCnameCopied ? Check : Copy" class="h-3 w-3" />
                                                        {{ isCnameCopied ? 'Copied' : 'Copy' }}
                                                    </Button>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            DNS changes can take up to 24 hours to propagate. Once it resolves, your visitors will see this agent's
                                            Hosted Chat on your domain.
                                        </p>
                                    </div>

                                    <!-- Domain input -->
                                    <div v-if="domain.has_quota" class="space-y-2">
                                        <Label for="custom-domain" class="text-xs">Step 2 — Enter the domain you pointed at us</Label>
                                        <div class="flex items-start gap-2">
                                            <div class="flex-1">
                                                <Input
                                                    id="custom-domain"
                                                    v-model="domainForm.domain"
                                                    type="text"
                                                    placeholder="chat.example.com"
                                                    autocomplete="off"
                                                    :disabled="locked || (domain.quota.available === 0 && !domain.value)"
                                                />
                                                <p v-if="domainForm.errors.domain" class="text-destructive mt-1 text-xs">
                                                    {{ domainForm.errors.domain }}
                                                </p>
                                            </div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                :disabled="!canEdit || isSavingDomain || domainForm.domain === (domain.value ?? '')"
                                                @click="saveDomain"
                                            >
                                                <Save class="mr-2 h-4 w-4" />
                                                {{ isSavingDomain ? 'Saving...' : 'Save' }}
                                            </Button>
                                            <Button
                                                v-if="domain.value"
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive"
                                                :disabled="locked || isSavingDomain"
                                                @click="clearDomain"
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            Enter the full hostname without scheme — e.g.
                                            <code class="bg-muted rounded px-1 py-0.5 text-xs">chat.example.com</code>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </ResizablePanel>

                <ResizableHandle with-handle @dragging="isPreviewResizing = $event" />

                <!-- Right column - Live Preview -->
                <ResizablePanel :default-size="38" :min-size="25">
                    <div class="bg-muted/40 flex h-full flex-col border-l">
                        <div class="border-border flex flex-shrink-0 items-center justify-between border-b px-4 py-2">
                            <div class="text-foreground text-sm font-medium">Live Preview</div>
                            <TooltipProvider :delay-duration="0">
                                <div class="flex items-center gap-1">
                                    <Tooltip v-for="d in ['desktop', 'tablet', 'mobile'] as const" :key="d">
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                @click="device = d"
                                                class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                                :class="{ 'text-foreground bg-background shadow-sm': device === d }"
                                            >
                                                <component :is="d === 'desktop' ? Monitor : d === 'tablet' ? Tablet : Smartphone" class="h-4 w-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p class="capitalize">{{ d }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                                :class="{ 'text-foreground': isExternalIdCopied }"
                                                @click="copyExternalId"
                                            >
                                                <component :is="isExternalIdCopied ? Check : Copy" class="h-4 w-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ isExternalIdCopied ? 'Copied!' : 'Copy agent ID' }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                                @click="refreshPreview"
                                            >
                                                <RotateCcw class="h-4 w-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Refresh preview</p>
                                        </TooltipContent>
                                    </Tooltip>
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground cursor-pointer rounded-md p-1.5 transition-colors"
                                                @click="openFullPreview"
                                            >
                                                <ExternalLink class="h-4 w-4" />
                                            </button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Open in new tab</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </TooltipProvider>
                        </div>
                        <div class="flex flex-1 items-center justify-center overflow-hidden p-4">
                            <div :class="frameClass" class="transition-all duration-300">
                                <iframe
                                    :key="`${previewSurface}-${device}-${previewKey}`"
                                    :src="previewUrl"
                                    class="h-full w-full border-0"
                                    :class="{ 'pointer-events-none': isPreviewResizing }"
                                    title="Surface preview"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                    </div>
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>

        <!-- JavaScript API Documentation Dialog (Widget + Bar both expose
             window.chatvia.* on the host page). -->
        <Dialog v-model:open="showApiDocsDialog">
            <DialogContent class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Terminal class="h-5 w-5" />
                        JavaScript API
                    </DialogTitle>
                    <DialogDescription>Control the chat widget programmatically using these methods and events</DialogDescription>
                </DialogHeader>

                <div class="flex-1 space-y-6 overflow-y-auto pr-2">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="h-4 w-4 text-amber-500" />
                            <h5 class="text-sm font-medium">Methods</h5>
                        </div>
                        <div class="overflow-hidden rounded-lg border">
                            <table class="w-full text-sm">
                                <thead class="bg-muted/50">
                                    <tr>
                                        <th class="text-muted-foreground px-3 py-2 text-left font-medium">Method</th>
                                        <th class="text-muted-foreground px-3 py-2 text-left font-medium">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="item in apiMethods" :key="item.method" class="hover:bg-muted/30 transition-colors">
                                        <td class="px-3 py-2">
                                            <code class="bg-muted text-primary rounded px-1.5 py-0.5 font-mono text-xs">{{ item.method }}</code>
                                        </td>
                                        <td class="text-muted-foreground px-3 py-2">{{ item.description }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="h-4 w-4 text-emerald-500" />
                            <h5 class="text-sm font-medium">Events</h5>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            Subscribe to widget events using
                            <code class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">window.chatvia.on(event, callback)</code>
                        </p>
                        <div class="relative">
                            <div class="border-border bg-muted/40 overflow-hidden rounded-lg border">
                                <div class="border-border bg-muted text-muted-foreground flex items-center justify-between border-b px-4 py-2">
                                    <span class="text-xs font-medium">JavaScript</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="hover:bg-background h-7 px-2"
                                        @click="copyApiCode(eventsCodeExample)"
                                    >
                                        <Copy class="mr-1.5 h-3.5 w-3.5" />
                                        <span class="text-xs">Copy</span>
                                    </Button>
                                </div>
                                <pre
                                    class="hljs overflow-x-auto bg-transparent p-4 text-sm leading-relaxed"
                                ><code class="language-javascript font-mono" v-html="highlightedEventsExample" /></pre>
                            </div>
                        </div>
                    </div>

                    <Alert class="bg-primary/5 border-primary/20">
                        <Info class="text-primary h-4 w-4" />
                        <AlertDescription class="text-sm">
                            <strong>Quick example:</strong> Open the chat when a button is clicked:
                            <code class="bg-muted mt-2 block rounded px-2 py-1 font-mono text-xs">
                                document.getElementById('help-btn').onclick = () =&gt; window.chatvia.open();
                            </code>
                        </AlertDescription>
                    </Alert>
                </div>

                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showApiDocsDialog = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
