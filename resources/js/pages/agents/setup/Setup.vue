<script lang="ts" setup>
import AgentPersonaEditor from '@/components/agents/AgentPersonaEditor.vue';
import BrandingSection from '@/components/agents/sections/BrandingSection.vue';
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
    Sparkles,
    Tablet,
    Terminal,
    Type,
    Zap,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
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
    canHideBranding: boolean;

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
const { t } = useI18n();

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

const surfaceTabs = computed(() => [
    { value: 'hosted_chat' as Surface, label: t('agents.setup.surfaces.chat'), icon: MessageSquare },
    { value: 'widget' as Surface, label: t('agents.setup.surfaces.widget'), icon: Globe },
    { value: 'bar' as Surface, label: t('agents.setup.surfaces.bar'), icon: Search },
]);

// ── Left sidebar
type MenuValue = 'info' | 'persona' | 'starters' | 'model' | 'branding' | 'behavior' | 'settings' | 'embed' | 'domain';
const activeTab = ref<MenuValue>('info');

const menuItems = computed(() => {
    const items: { value: MenuValue; label: string; icon: typeof MessageSquare }[] = [
        { value: 'info', label: t('agents.setup.menu.info'), icon: Type },
        { value: 'persona', label: t('agents.setup.menu.persona'), icon: Bot },
        { value: 'starters', label: t('agents.setup.menu.starters'), icon: MessageSquare },
        { value: 'model', label: t('agents.setup.menu.model'), icon: Sparkles },
        { value: 'branding', label: t('agents.setup.menu.branding'), icon: ImageIcon },
        { value: 'behavior', label: t('agents.setup.menu.behavior'), icon: Settings },
        { value: 'settings', label: t('agents.setup.menu.settings'), icon: SlidersHorizontal },
        // Embed/install moved to its own top-level "Deploy" page (agents.deploy).
    ];

    if (previewSurface.value === 'hosted_chat') {
        items.push({ value: 'domain', label: t('agents.setup.menu.domain'), icon: Globe });
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
            toast.success(t('agents.setup.toasts.text_saved'));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.text_error')),
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
            toast.success(t('agents.setup.toasts.composer_saved'));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.composer_error')),
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
            onSuccess: () => toast.success(t('agents.setup.toasts.chatlog_saved')),
            onError: () => toast.error(t('agents.setup.toasts.chatlog_error')),
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
        onSuccess: () => toast.success(t('agents.setup.toasts.analytics_saved')),
        onError: () => toast.error(t('agents.setup.toasts.analytics_error')),
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
        toast.success(t('agents.setup.toasts.starters_saved'));
        bumpPreview();
    } catch {
        toast.error(t('agents.setup.toasts.starters_error'));
    } finally {
        isSavingStarters.value = false;
    }
};

// ── Answer temperature → agents.temperature.update. The model itself is
// platform-managed and intentionally never exposed or editable here; owners
// only tune answer style via temperature.
const temperatureForm = useForm({
    temperature: props.llmSettings.temperature,
});
const isSavingTemperature = ref(false);

const saveTemperature = () => {
    isSavingTemperature.value = true;
    temperatureForm.patch(route('agents.temperature.update', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('agents.setup.toasts.saved')),
        onError: () => toast.error(t('agents.setup.toasts.save_error')),
        onFinish: () => (isSavingTemperature.value = false),
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
        onSuccess: () => toast.success(domainForm.domain ? t('agents.setup.toasts.domain_saved') : t('agents.setup.toasts.domain_removed')),
        onError: () => toast.error(typeof domainForm.errors.domain === 'string' ? domainForm.errors.domain : t('agents.setup.toasts.domain_error')),
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
        toast.error(t('agents.setup.toasts.copy_error'));
    }
};

// ── Branding (colors + avatar + logo) → appearance group
const brandingForm = useForm({
    color_theme: str(a.color_theme, str(ad.color_theme, 'black')),
    color_shade: num(a.color_shade, num(ad.color_shade, 700)),
    // Only meaningful for workspaces that can hide branding; defaults to hidden.
    show_branding: bool(a.show_branding, bool(ad.show_branding, false)),
});
const avatarPreview = ref<string | null>(str(a.avatar) || null);
const logoPreview = ref<string | null>(str(a.logo) || null);
// After an upload we `router.reload({ only: ['appearanceSettings'] })`; mirror the
// refreshed prop back into the local previews so the new image shows immediately.
// Each upload is stored under a unique hashed path, so the URL always changes —
// no cache-busting needed.
watch(
    () => props.appearanceSettings,
    (next) => {
        avatarPreview.value = str(next.avatar) || null;
        logoPreview.value = str(next.logo) || null;
    },
);
const isSavingBranding = ref(false);
const postBranding = (toastKey: string) => {
    isSavingBranding.value = true;
    brandingForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t(toastKey));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.colors_error')),
        onFinish: () => (isSavingBranding.value = false),
    });
};
const saveBranding = () => postBranding('agents.setup.toasts.colors_saved');
// The badge show/hide toggle saves on change (no extra Save button).
const saveBrandingVisibility = (value: boolean) => {
    brandingForm.show_branding = value;
    postBranding('agents.setup.toasts.branding_saved');
};
const uploadImage = (key: 'avatar' | 'logo', file: File) => {
    const upload = useForm({ [key]: file });
    upload.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success(key === 'avatar' ? t('agents.setup.toasts.avatar_updated') : t('agents.setup.toasts.logo_updated'));
            router.reload({ only: ['appearanceSettings'] });
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.upload_error', { key })),
    });
};
const removeImage = async (key: 'avatar' | 'logo') => {
    try {
        await axios.delete(route('agents.settings.delete', { agent: props.agent.external_id, group: 'appearance' }), {
            data: { key },
        });
        if (key === 'avatar') avatarPreview.value = null;
        else logoPreview.value = null;
        toast.success(key === 'avatar' ? t('agents.setup.toasts.avatar_removed') : t('agents.setup.toasts.logo_removed'));
        bumpPreview();
    } catch {
        toast.error(t('agents.setup.toasts.remove_error', { key }));
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
            toast.success(t('agents.setup.toasts.hosted_saved'));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.behaviour_error')),
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
            toast.success(t('agents.setup.toasts.bubbles_saved'));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.bubbles_error')),
        onFinish: () => (isSavingBubbles.value = false),
    });
};
const isSavingWidgetBehavior = ref(false);
const saveWidgetBehavior = () => {
    isSavingWidgetBehavior.value = true;
    widgetBehaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(t('agents.setup.toasts.widget_saved'));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.behaviour_error')),
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
            toast.success(t('agents.setup.toasts.bar_saved'));
            bumpPreview();
        },
        onError: () => toast.error(t('agents.setup.toasts.behaviour_error')),
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
const placementOptions = computed(() => [
    { value: 'top', label: t('agents.setup.options.placement_top') },
    { value: 'top-right', label: t('agents.setup.options.placement_top_right') },
    { value: 'bottom', label: t('agents.setup.options.placement_bottom') },
    { value: 'bottom-right', label: t('agents.setup.options.placement_bottom_right') },
]);
const positionOptions = computed(() => [
    { value: 'bottom-right', label: t('agents.setup.options.position_bottom_right') },
    { value: 'bottom-left', label: t('agents.setup.options.position_bottom_left') },
    { value: 'top-right', label: t('agents.setup.options.position_top_right') },
    { value: 'top-left', label: t('agents.setup.options.position_top_left') },
]);
const windowSizeOptions = computed(() => [
    { value: 'small', label: t('agents.setup.options.size_small') },
    { value: 'medium', label: t('agents.setup.options.size_medium') },
    { value: 'large', label: t('agents.setup.options.size_large') },
]);
const themeOptions = computed(() => [
    { value: 'auto', label: t('agents.setup.options.theme_auto') },
    { value: 'light', label: t('agents.setup.options.theme_light') },
    { value: 'dark', label: t('agents.setup.options.theme_dark') },
]);

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
    title: '',
});
watchEffect(() => {
    iframeOptions.title = t('agents.setup.embed.iframe_title', { name: props.agent.name });
});
const protectedSurfaceLabel = computed(() => {
    if (previewSurface.value === 'widget') return t('agents.setup.surfaces.widget');
    if (previewSurface.value === 'bar') return t('agents.setup.surfaces.bar');
    return previewSurface.value;
});
const domainUsedCount = computed(() => props.domain.quota.used + (props.domain.value ? 1 : 0));
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
        toast.success(t('agents.setup.toasts.embed_copied'));
        setTimeout(() => (iframeCopied.value = false), 2000);
    } catch {
        toast.error(t('agents.setup.toasts.copy_failed'));
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
        toast.success(t('agents.setup.toasts.copied_clipboard'));
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        toast.error(t('agents.setup.toasts.copy_failed'));
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
        toast.success(t('agents.setup.toasts.agent_id_copied'));
        setTimeout(() => (isExternalIdCopied.value = false), 2000);
    } catch {
        toast.error(t('agents.setup.toasts.copy_error'));
    }
};

// ── JavaScript API reference — shown for Widget + Bar (both surfaces install
// via the same `window.chatvia` script global). Hosted Chat is a full-page
// URL, no JS API; the template branch for hosted_chat doesn't render the
// trigger button.
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

const copyApiCode = async (code: string) => {
    try {
        await navigator.clipboard.writeText(code);
        toast.success(t('agents.setup.toasts.code_copied'));
    } catch {
        toast.error(t('agents.setup.toasts.code_copy_error'));
    }
};
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.setup.title')" hide-header no-padding full-height>
        <div class="bg-background flex h-full flex-col">
            <!-- Preview-tab bar — local UI state. Setup-style underline nav. -->
            <div class="bg-background border-border flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b pe-4 lg:pe-6">
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
                        <span v-if="previewSurface === tab.value" class="bg-foreground absolute start-0 end-0 -bottom-px h-0.5"></span>
                    </button>
                </nav>
            </div>

            <!-- Layout: (left sidebar + middle content) | drag-resizable | right live preview -->
            <ResizablePanelGroup direction="horizontal" auto-save-id="agent-setup-preview" class="min-w-0 flex-1">
                <ResizablePanel :default-size="62" :min-size="35">
                    <div class="flex h-full min-h-0">
                        <!-- Left sidebar -->
                        <div class="bg-muted/30 flex h-full w-[180px] flex-shrink-0 flex-col border-e">
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
                                <AlertTitle>{{ t('agents.setup.view_only_title') }}</AlertTitle>
                                <AlertDescription>{{ t('agents.setup.view_only_description') }}</AlertDescription>
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
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingText ? t('agents.setup.saving') : t('common.save') }}
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
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.settings.composer_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                {{ t('agents.setup.settings.composer_description') }}
                                            </p>
                                        </div>
                                        <div class="divide-y rounded-md border">
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="allow-file-uploads" class="cursor-pointer text-sm font-medium">{{
                                                            t('agents.setup.settings.file_uploads_label')
                                                        }}</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    :aria-label="t('agents.setup.settings.file_uploads_aria')"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">
                                                                    {{ t('agents.setup.settings.file_uploads_help_title') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.file_uploads_help_1') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.file_uploads_help_2') }}
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.settings.file_uploads_hint') }}
                                                    </p>
                                                </div>
                                                <Switch id="allow-file-uploads" v-model="composerForm.allow_file_uploads" :disabled="locked" />
                                            </div>
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <Label for="allow-voice-input" class="cursor-pointer text-sm font-medium">{{
                                                        t('agents.setup.settings.voice_input_label')
                                                    }}</Label>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.settings.voice_input_hint') }}
                                                    </p>
                                                </div>
                                                <Switch id="allow-voice-input" v-model="composerForm.allow_voice_input" :disabled="locked" />
                                            </div>
                                        </div>
                                        <div v-if="!locked">
                                            <Button type="submit" :disabled="!canEdit || isSavingComposer" size="sm">
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingComposer ? t('agents.setup.saving') : t('common.save') }}
                                            </Button>
                                        </div>
                                    </form>

                                    <Separator />

                                    <!-- Chat log — master switch for persisting conversations. Off
                                         stops StoreMessageJob, so nothing is logged. -->
                                    <form @submit.prevent="saveChatlog" class="space-y-3">
                                        <div>
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.settings.chatlog_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                {{ t('agents.setup.settings.chatlog_description') }}
                                            </p>
                                        </div>
                                        <div class="divide-y rounded-md border">
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="chatlog-enabled" class="cursor-pointer text-sm font-medium">{{
                                                            t('agents.setup.settings.store_conversations_label')
                                                        }}</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    :aria-label="t('agents.setup.settings.store_conversations_aria')"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">
                                                                    {{ t('agents.setup.settings.store_conversations_help_title') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.store_conversations_help_1') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.store_conversations_help_2') }}
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.settings.store_conversations_hint') }}
                                                    </p>
                                                </div>
                                                <Switch id="chatlog-enabled" v-model="chatlogForm.chatlog" :disabled="locked" />
                                            </div>
                                        </div>
                                        <div v-if="!locked">
                                            <Button type="submit" :disabled="!canEdit || isSavingChatlog" size="sm">
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingChatlog ? t('agents.setup.saving') : t('common.save') }}
                                            </Button>
                                        </div>
                                    </form>

                                    <Separator />

                                    <!-- Analytics — per-agent on/off for the optional LLM-powered
                                         conversation insights. Default on; off stops the analysis. -->
                                    <form @submit.prevent="saveAnalytics" class="space-y-3">
                                        <div>
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.settings.analytics_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                {{ t('agents.setup.settings.analytics_description') }}
                                            </p>
                                        </div>
                                        <div class="divide-y rounded-md border" :class="{ 'opacity-60': !chatlogForm.chatlog }">
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="analytics-sentiment" class="cursor-pointer text-sm font-medium">{{
                                                            t('agents.setup.settings.sentiment_label')
                                                        }}</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    :aria-label="t('agents.setup.settings.sentiment_aria')"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">
                                                                    {{ t('agents.setup.settings.sentiment_help_title') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.sentiment_help_1') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.sentiment_help_2') }}
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.settings.sentiment_hint') }}
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
                                                        <Label for="analytics-tone" class="cursor-pointer text-sm font-medium">{{
                                                            t('agents.setup.settings.tone_label')
                                                        }}</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    :aria-label="t('agents.setup.settings.tone_aria')"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">
                                                                    {{ t('agents.setup.settings.tone_help_title') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.tone_help_1') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.tone_help_2') }}
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.settings.tone_hint') }}
                                                    </p>
                                                </div>
                                                <Switch id="analytics-tone" v-model="analyticsForm.tone" :disabled="locked || !chatlogForm.chatlog" />
                                            </div>
                                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <Label for="analytics-topics" class="cursor-pointer text-sm font-medium">{{
                                                            t('agents.setup.settings.topics_label')
                                                        }}</Label>
                                                        <Popover>
                                                            <PopoverTrigger as-child>
                                                                <button
                                                                    type="button"
                                                                    class="text-muted-foreground hover:text-foreground cursor-pointer rounded-full transition-colors"
                                                                    :aria-label="t('agents.setup.settings.topics_aria')"
                                                                >
                                                                    <CircleHelp class="h-3.5 w-3.5" />
                                                                </button>
                                                            </PopoverTrigger>
                                                            <PopoverContent align="start" class="w-80 text-xs leading-relaxed">
                                                                <p class="text-foreground font-medium">
                                                                    {{ t('agents.setup.settings.topics_help_title') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.topics_help_1') }}
                                                                </p>
                                                                <p class="text-muted-foreground mt-1.5">
                                                                    {{ t('agents.setup.settings.topics_help_2') }}
                                                                </p>
                                                            </PopoverContent>
                                                        </Popover>
                                                    </div>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.settings.topics_hint') }}
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
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingAnalytics ? t('agents.setup.saving') : t('common.save') }}
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
                                            <Save class="me-2 h-4 w-4" />
                                            {{ isSavingStarters ? t('agents.setup.saving') : t('common.save') }}
                                        </Button>
                                    </div>

                                    <!-- Chat Bubbles — widget surface only. Hosted Chat and Bar don't
                                         render quick-message bubbles next to the launcher, so the field
                                         would just be dead settings noise on those tabs. -->
                                    <template v-if="previewSurface === 'widget'">
                                        <Separator />

                                        <div class="space-y-4">
                                            <div>
                                                <h3 class="text-sm font-medium">{{ t('agents.setup.bubbles_title') }}</h3>
                                                <p class="text-muted-foreground mt-1 text-xs">
                                                    {{ t('agents.setup.bubbles_description') }}
                                                </p>
                                            </div>

                                            <TagsInput v-model="bubblesForm.bubbles" :disabled="locked" class="w-full">
                                                <TagsInputItem v-for="(bubble, index) in bubblesForm.bubbles" :key="index" :value="bubble">
                                                    <TagsInputItemText />
                                                    <TagsInputItemDelete />
                                                </TagsInputItem>
                                                <TagsInputInput add-on-blur :placeholder="t('agents.setup.bubbles_placeholder')" />
                                            </TagsInput>
                                            <p v-if="bubblesForm.errors.bubbles" class="text-destructive text-sm">
                                                {{ bubblesForm.errors.bubbles }}
                                            </p>

                                            <div v-if="!locked">
                                                <Button type="button" size="sm" :disabled="!canEdit || isSavingBubbles" @click="saveBubbles">
                                                    <Save class="me-2 h-4 w-4" />
                                                    {{ isSavingBubbles ? t('agents.setup.saving') : t('common.save') }}
                                                </Button>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <!-- Model — answer temperature only (the model is platform-managed) -->
                                <div v-if="activeTab === 'model'" class="space-y-6 p-6">
                                    <div class="space-y-1">
                                        <h3 class="text-sm font-medium">{{ t('agents.setup.model_title') }}</h3>
                                        <p class="text-muted-foreground text-sm">
                                            {{ t('agents.setup.model_description') }}
                                        </p>
                                    </div>

                                    <div class="max-w-md space-y-3">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="font-medium">{{ t('agents.setup.temperature') }}</span>
                                            <span class="text-muted-foreground tabular-nums">{{ temperatureForm.temperature.toFixed(1) }}</span>
                                        </div>
                                        <input
                                            v-model.number="temperatureForm.temperature"
                                            type="range"
                                            min="0"
                                            max="2"
                                            step="0.1"
                                            :disabled="locked || !canEdit"
                                            class="accent-primary h-2 w-full cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                        />
                                        <div class="text-muted-foreground flex justify-between text-xs">
                                            <span>{{ t('agents.setup.temperature_precise') }}</span>
                                            <span>{{ t('agents.setup.temperature_balanced') }}</span>
                                            <span>{{ t('agents.setup.temperature_creative') }}</span>
                                        </div>
                                        <p v-if="temperatureForm.errors.temperature" class="text-destructive text-sm">
                                            {{ temperatureForm.errors.temperature }}
                                        </p>
                                    </div>

                                    <div v-if="!locked" class="border-t pt-4">
                                        <Button type="button" size="sm" :disabled="!canEdit || isSavingTemperature" @click="saveTemperature">
                                            <Save class="me-2 h-4 w-4" />
                                            {{ isSavingTemperature ? t('agents.setup.saving') : t('common.save') }}
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
                                        :can-hide-branding="canHideBranding"
                                        :show-branding="brandingForm.show_branding"
                                        :errors="brandingForm.errors as Record<string, string>"
                                        :disabled="locked || isSavingBranding"
                                        @color-apply="saveBranding"
                                        @update:show-branding="saveBrandingVisibility"
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
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.behavior.hosted_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.behavior.hosted_description') }}</p>
                                        </div>
                                        <div class="max-w-3xl space-y-5">
                                            <div class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                                <div class="space-y-1.5">
                                                    <Label for="hosted-default-theme" class="text-sm font-medium">{{
                                                        t('agents.setup.behavior.default_theme')
                                                    }}</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                        >
                                                            <Palette class="h-4 w-4" />
                                                        </div>
                                                        <Select v-model="hostedBehaviorForm.default_theme" :disabled="locked">
                                                            <SelectTrigger id="hosted-default-theme" class="h-10 w-full ps-9">
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
                                                    <Label for="hosted-language" class="text-sm font-medium">{{
                                                        t('agents.setup.behavior.language')
                                                    }}</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                        >
                                                            <Languages class="h-4 w-4" />
                                                        </div>
                                                        <Select v-model="hostedBehaviorForm.language" :disabled="locked">
                                                            <SelectTrigger id="hosted-language" class="h-10 w-full ps-9"
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
                                                    <Label for="hosted-remove-sidebar" class="cursor-pointer text-sm font-medium">{{
                                                        t('agents.setup.behavior.remove_sidebar')
                                                    }}</Label>
                                                    <Switch
                                                        id="hosted-remove-sidebar"
                                                        v-model="hostedBehaviorForm.remove_sidebar"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-hide-sidebar" class="cursor-pointer text-sm font-medium">{{
                                                        t('agents.setup.behavior.hide_sidebar')
                                                    }}</Label>
                                                    <Switch
                                                        id="hosted-hide-sidebar"
                                                        v-model="hostedBehaviorForm.hide_sidebar_on_page_load"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-theme-switcher" class="cursor-pointer text-sm font-medium">{{
                                                        t('agents.setup.behavior.theme_switcher')
                                                    }}</Label>
                                                    <Switch
                                                        id="hosted-theme-switcher"
                                                        v-model="hostedBehaviorForm.enable_theme_switcher"
                                                        :disabled="locked"
                                                    />
                                                </div>
                                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                                    <Label for="hosted-chat-expand" class="cursor-pointer text-sm font-medium">{{
                                                        t('agents.setup.behavior.chat_expand')
                                                    }}</Label>
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
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingHostedBehavior ? t('agents.setup.saving') : t('common.save') }}
                                            </Button>
                                        </div>
                                    </template>

                                    <!-- Widget -->
                                    <template v-else-if="previewSurface === 'widget'">
                                        <div>
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.behavior.widget_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                {{ t('agents.setup.behavior.widget_description') }}
                                            </p>
                                        </div>
                                        <div class="grid max-w-3xl gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="space-y-1.5">
                                                <Label for="widget-position" class="text-sm font-medium">{{
                                                    t('agents.setup.behavior.position')
                                                }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                    >
                                                        <MapPin class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.position" :disabled="locked">
                                                        <SelectTrigger id="widget-position" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="p in positionOptions" :key="p.value" :value="p.value">{{
                                                                p.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="widget-size" class="text-sm font-medium">{{
                                                    t('agents.setup.behavior.window_size')
                                                }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                    >
                                                        <Maximize2 class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.window_size" :disabled="locked">
                                                        <SelectTrigger id="widget-size" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="s in windowSizeOptions" :key="s.value" :value="s.value">{{
                                                                s.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="widget-theme" class="text-sm font-medium">{{ t('agents.setup.behavior.theme') }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                    >
                                                        <Palette class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.theme" :disabled="locked">
                                                        <SelectTrigger id="widget-theme" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="t in themeOptions" :key="t.value" :value="t.value">{{
                                                                t.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="widget-language" class="text-sm font-medium">{{
                                                    t('agents.setup.behavior.language')
                                                }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                    >
                                                        <Languages class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="widgetBehaviorForm.language" :disabled="locked">
                                                        <SelectTrigger id="widget-language" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
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
                                                    <Label for="widget-handoff" class="cursor-pointer text-sm font-medium">{{
                                                        t('agents.setup.behavior.live_chat')
                                                    }}</Label>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ t('agents.setup.behavior.live_chat_hint') }}
                                                    </p>
                                                </div>
                                                <Switch id="widget-handoff" v-model="widgetBehaviorForm.handoff_enabled" :disabled="locked" />
                                            </div>
                                            <div
                                                class="bg-muted/40 flex items-center justify-between gap-3 rounded-md border px-4 py-3 sm:col-span-2"
                                            >
                                                <Label for="widget-show-on-load" class="cursor-pointer text-sm font-medium">{{
                                                    t('agents.setup.behavior.open_on_load')
                                                }}</Label>
                                                <Switch id="widget-show-on-load" v-model="widgetBehaviorForm.show_on_load" :disabled="locked" />
                                            </div>
                                            <div v-if="widgetBehaviorForm.show_on_load" class="space-y-1.5 sm:col-span-2">
                                                <Label for="widget-delay" class="text-sm font-medium">{{
                                                    t('agents.setup.behavior.delay_ms')
                                                }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3"
                                                    >
                                                        <Clock class="h-4 w-4" />
                                                    </div>
                                                    <Input
                                                        id="widget-delay"
                                                        type="number"
                                                        min="0"
                                                        v-model="widgetBehaviorForm.show_on_load_delay"
                                                        :disabled="locked"
                                                        class="h-10 ps-9"
                                                    />
                                                </div>
                                            </div>
                                            <div class="space-y-1.5 sm:col-span-2">
                                                <Label for="widget-sites" class="text-sm font-medium"
                                                    >{{ t('agents.setup.behavior.allowed_sites') }}
                                                    <span class="text-muted-foreground font-normal">{{
                                                        t('agents.setup.behavior.allowed_sites_hint')
                                                    }}</span></Label
                                                >
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute start-0 top-0 flex items-center ps-3 pt-2.5"
                                                    >
                                                        <Globe class="h-4 w-4" />
                                                    </div>
                                                    <textarea
                                                        id="widget-sites"
                                                        v-model="sitesText"
                                                        rows="4"
                                                        :disabled="locked"
                                                        class="border-input bg-background placeholder:text-muted-foreground focus-visible:ring-ring w-full rounded-md border py-2 ps-9 pe-3 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                                        :placeholder="t('agents.setup.behavior.allowed_sites_placeholder')"
                                                    />
                                                </div>
                                                <p class="text-muted-foreground text-xs">{{ t('agents.setup.behavior.allowed_sites_help') }}</p>
                                            </div>
                                        </div>
                                        <div v-if="!locked" class="border-t pt-4">
                                            <Button
                                                type="button"
                                                size="sm"
                                                :disabled="!canEdit || isSavingWidgetBehavior"
                                                @click="saveWidgetBehavior"
                                            >
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingWidgetBehavior ? t('agents.setup.saving') : t('common.save') }}
                                            </Button>
                                        </div>
                                    </template>

                                    <!-- Bar -->
                                    <template v-else>
                                        <div>
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.behavior.bar_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">{{ t('agents.setup.behavior.bar_description') }}</p>
                                        </div>
                                        <div class="grid max-w-3xl gap-x-6 gap-y-5 sm:grid-cols-2">
                                            <div class="space-y-1.5">
                                                <Label for="bar-trigger" class="text-sm font-medium">{{
                                                    t('agents.setup.behavior.keyboard_trigger')
                                                }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                    >
                                                        <Keyboard class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="barBehaviorForm.trigger" :disabled="locked">
                                                        <SelectTrigger id="bar-trigger" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem v-for="t in triggerOptions" :key="t.value" :value="t.value">{{
                                                                t.label
                                                            }}</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>
                                            <div class="space-y-1.5">
                                                <Label for="bar-placement" class="text-sm font-medium">{{
                                                    t('agents.setup.behavior.placement')
                                                }}</Label>
                                                <div class="relative">
                                                    <div
                                                        class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 z-10 flex items-center ps-3"
                                                    >
                                                        <LayoutGrid class="h-4 w-4" />
                                                    </div>
                                                    <Select v-model="barBehaviorForm.placement" :disabled="locked">
                                                        <SelectTrigger id="bar-placement" class="h-10 w-full ps-9"><SelectValue /></SelectTrigger>
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
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingBarBehavior ? t('agents.setup.saving') : t('common.save') }}
                                            </Button>
                                        </div>
                                    </template>
                                </div>

                                <!-- Embed — swaps with previewSurface -->
                                <div v-if="activeTab === 'embed'" class="space-y-4 p-6">
                                    <!-- Hosted Chat — shareable agent URL with a side-by-side copy button. -->
                                    <template v-if="previewSurface === 'hosted_chat'">
                                        <div>
                                            <Label class="text-base">{{ t('agents.setup.embed.agent_url_title') }}</Label>
                                            <p class="text-muted-foreground mt-1 mb-3 text-sm">{{ t('agents.setup.embed.agent_url_description') }}</p>
                                        </div>

                                        <div class="bg-muted rounded-lg p-4">
                                            <div class="flex items-center justify-between gap-3">
                                                <code class="text-foreground text-sm break-all">{{ shareUrl }}</code>
                                                <Button type="button" variant="outline" size="sm" @click="copyActive" class="shrink-0 gap-2">
                                                    <component :is="copied ? Check : Copy" class="h-4 w-4" />
                                                    {{ copied ? t('agents.setup.copied') : t('workspace.common.copy') }}
                                                </Button>
                                            </div>
                                        </div>

                                        <Alert>
                                            <Info class="h-4 w-4" />
                                            <AlertDescription>
                                                {{ t('agents.setup.embed.agent_url_alert') }}
                                            </AlertDescription>
                                        </Alert>

                                        <!-- Iframe embed configurator — width/height + presets, generated <iframe>. -->
                                        <div class="space-y-3 border-t pt-4">
                                            <Label class="text-sm font-medium">{{ t('agents.setup.embed.config_title') }}</Label>
                                            <p class="text-muted-foreground text-xs">
                                                {{ t('agents.setup.embed.config_description') }}
                                            </p>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="space-y-1.5">
                                                    <Label for="iframe-width" class="text-xs">{{ t('agents.setup.embed.width') }}</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5"
                                                        >
                                                            <MoveHorizontal class="h-3.5 w-3.5" />
                                                        </div>
                                                        <Input
                                                            id="iframe-width"
                                                            v-model="iframeOptions.width"
                                                            type="text"
                                                            :placeholder="t('agents.setup.embed.width_placeholder')"
                                                            class="ps-8 text-sm"
                                                        />
                                                    </div>
                                                </div>
                                                <div class="space-y-1.5">
                                                    <Label for="iframe-height" class="text-xs">{{ t('agents.setup.embed.height') }}</Label>
                                                    <div class="relative">
                                                        <div
                                                            class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-2.5"
                                                        >
                                                            <MoveVertical class="h-3.5 w-3.5" />
                                                        </div>
                                                        <Input
                                                            id="iframe-height"
                                                            v-model="iframeOptions.height"
                                                            type="text"
                                                            :placeholder="t('agents.setup.embed.height_placeholder')"
                                                            class="ps-8 text-sm"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-4 gap-2">
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('full')">{{
                                                    t('agents.setup.embed.preset_full')
                                                }}</Button>
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('large')">{{
                                                    t('agents.setup.embed.preset_large')
                                                }}</Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="text-xs"
                                                    @click="setIframePreset('medium')"
                                                    >{{ t('agents.setup.embed.preset_medium') }}</Button
                                                >
                                                <Button type="button" variant="outline" size="sm" class="text-xs" @click="setIframePreset('small')">{{
                                                    t('agents.setup.embed.preset_small')
                                                }}</Button>
                                            </div>
                                        </div>

                                        <div class="bg-muted space-y-3 rounded-lg p-4">
                                            <div class="flex items-center justify-between">
                                                <p class="text-muted-foreground text-sm">{{ t('agents.setup.embed.html_code') }}</p>
                                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="copyIframe">
                                                    <component :is="iframeCopied ? Check : Copy" class="h-4 w-4" />
                                                    {{ iframeCopied ? t('agents.setup.copied') : t('workspace.common.copy') }}
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
                                            <h3 class="text-sm font-medium">{{ t('agents.setup.embed.install_title') }}</h3>
                                            <p class="text-muted-foreground mt-1 text-xs">
                                                {{ t('agents.setup.embed.install_description') }}
                                            </p>
                                        </div>
                                        <div class="bg-muted space-y-3 rounded-lg p-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="copyActive">
                                                    <component :is="copied ? Check : Copy" class="h-4 w-4" />
                                                    {{ copied ? t('agents.setup.copied') : t('workspace.common.copy') }}
                                                </Button>
                                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="openFullPreview">
                                                    <Eye class="h-4 w-4" />
                                                    {{ t('agents.setup.embed.full_preview') }}
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
                                            <AlertTitle>{{ t('agents.setup.embed.protected_title', { surface: protectedSurfaceLabel }) }}</AlertTitle>
                                            <AlertDescription>
                                                {{ t('agents.setup.embed.protected_description') }}
                                                <Link
                                                    :href="route('agents.identity', { agent: agent.external_id })"
                                                    class="text-primary font-medium hover:underline"
                                                >
                                                    {{ t('agents.setup.embed.open_security') }}
                                                </Link>
                                            </AlertDescription>
                                        </Alert>

                                        <Button type="button" variant="outline" class="w-full justify-start gap-2" @click="showApiDocsDialog = true">
                                            <Terminal class="h-4 w-4" />
                                            {{ t('agents.setup.embed.api_docs') }}
                                        </Button>
                                    </template>
                                </div>

                                <!-- Domain — Hosted Chat only. Gated by Custom Domain subscription / Pro plan quota. -->
                                <div v-if="activeTab === 'domain'" class="space-y-6 p-6">
                                    <div>
                                        <h3 class="text-sm font-medium">{{ t('agents.setup.domain.title') }}</h3>
                                        <p class="text-muted-foreground mt-1 text-xs">
                                            {{ t('agents.setup.domain.description', { target: domain.cname_target }) }}
                                        </p>
                                    </div>

                                    <!-- Subscription gate -->
                                    <Alert v-if="!domain.has_quota" class="border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30">
                                        <Globe class="h-4 w-4" />
                                        <AlertTitle>{{ t('agents.setup.domain.no_quota_title') }}</AlertTitle>
                                        <AlertDescription>
                                            <i18n-t keypath="agents.setup.domain.no_quota_description" tag="span" scope="global">
                                                <template #link>
                                                    <Link :href="route('billing.index')" class="text-primary font-medium hover:underline">
                                                        {{ t('agents.setup.domain.billing') }}
                                                    </Link>
                                                </template>
                                            </i18n-t>
                                        </AlertDescription>
                                    </Alert>

                                    <!-- Quota counter -->
                                    <div v-else class="bg-muted/40 flex items-center justify-between rounded-lg border p-3 text-sm">
                                        <span class="text-muted-foreground">
                                            {{
                                                t(
                                                    'agents.setup.domain.quota',
                                                    { used: domainUsedCount, total: domain.quota.total },
                                                    { plural: domain.quota.total },
                                                )
                                            }}
                                        </span>
                                        <Link
                                            v-if="domain.quota.available === 0 && !domain.value"
                                            :href="route('billing.index')"
                                            class="text-primary text-xs font-medium hover:underline"
                                        >
                                            {{ t('agents.setup.domain.add_more') }}
                                        </Link>
                                    </div>

                                    <!-- CNAME instructions -->
                                    <div v-if="domain.has_quota" class="space-y-3">
                                        <Label class="text-xs">{{ t('agents.setup.domain.step1') }}</Label>
                                        <div class="bg-muted overflow-x-auto rounded-lg border p-4 font-mono text-xs">
                                            <div class="grid grid-cols-[80px_1fr] gap-x-4 gap-y-1">
                                                <span class="text-muted-foreground">{{ t('agents.setup.domain.type') }}</span
                                                ><span class="font-semibold">CNAME</span>
                                                <span class="text-muted-foreground">{{ t('agents.setup.domain.name') }}</span
                                                ><span>{{ t('agents.setup.domain.cname_name') }}</span>
                                                <span class="text-muted-foreground">{{ t('agents.setup.domain.value') }}</span>
                                                <span class="flex items-center gap-2">
                                                    <code class="font-semibold">{{ domain.cname_target }}</code>
                                                    <Button type="button" variant="ghost" size="sm" class="h-6 gap-1 px-2" @click="copyCnameTarget">
                                                        <component :is="isCnameCopied ? Check : Copy" class="h-3 w-3" />
                                                        {{ isCnameCopied ? t('agents.setup.copied') : t('workspace.common.copy') }}
                                                    </Button>
                                                </span>
                                            </div>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            {{ t('agents.setup.domain.dns_hint') }}
                                        </p>
                                    </div>

                                    <!-- Domain input -->
                                    <div v-if="domain.has_quota" class="space-y-2">
                                        <Label for="custom-domain" class="text-xs">{{ t('agents.setup.domain.step2') }}</Label>
                                        <div class="flex items-start gap-2">
                                            <div class="flex-1">
                                                <Input
                                                    id="custom-domain"
                                                    v-model="domainForm.domain"
                                                    type="text"
                                                    :placeholder="t('agents.setup.domain.placeholder')"
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
                                                <Save class="me-2 h-4 w-4" />
                                                {{ isSavingDomain ? t('agents.setup.saving') : t('common.save') }}
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
                                                {{ t('common.remove') }}
                                            </Button>
                                        </div>
                                        <p class="text-muted-foreground text-xs">
                                            {{ t('agents.setup.domain.hostname_hint') }}
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
                    <div class="bg-muted/40 flex h-full flex-col border-s">
                        <div class="border-border flex flex-shrink-0 items-center justify-between border-b px-4 py-2">
                            <div class="text-foreground text-sm font-medium">{{ t('agents.setup.preview.title') }}</div>
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
                                            <p>
                                                {{ isExternalIdCopied ? t('agents.setup.preview.copied') : t('agents.setup.preview.copy_agent_id') }}
                                            </p>
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
                                            <p>{{ t('agents.setup.preview.refresh') }}</p>
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
                                            <p>{{ t('agents.setup.preview.open_new_tab') }}</p>
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
                                    :title="t('agents.setup.embed.surface_preview')"
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
                        {{ t('agents.setup.api.title') }}
                    </DialogTitle>
                    <DialogDescription>{{ t('agents.setup.api.description') }}</DialogDescription>
                </DialogHeader>

                <div class="flex-1 space-y-6 overflow-y-auto pe-2">
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <Zap class="h-4 w-4 text-amber-500" />
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
                            <h5 class="text-sm font-medium">{{ t('agents.setup.api.events') }}</h5>
                        </div>
                        <p class="text-muted-foreground text-sm">
                            {{ t('agents.setup.api.events_hint') }}
                            <code class="bg-muted rounded px-1.5 py-0.5 font-mono text-xs">window.chatvia.on(event, callback)</code>
                        </p>
                        <div class="relative">
                            <div class="border-border bg-muted/40 overflow-hidden rounded-lg border">
                                <div class="border-border bg-muted text-muted-foreground flex items-center justify-between border-b px-4 py-2">
                                    <span class="text-xs font-medium">{{ t('agents.setup.api.code_language') }}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="hover:bg-background h-7 px-2"
                                        @click="copyApiCode(eventsCodeExample)"
                                    >
                                        <Copy class="me-1.5 h-3.5 w-3.5" />
                                        <span class="text-xs">{{ t('workspace.common.copy') }}</span>
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
                            <strong>{{ t('agents.setup.api.quick_example') }}</strong> {{ t('agents.setup.api.quick_example_text') }}
                            <code class="bg-muted mt-2 block rounded px-2 py-1 font-mono text-xs">
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
