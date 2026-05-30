<script lang="ts" setup>
import BrandingSection from '@/components/agents/sections/BrandingSection.vue';
import StartersSection from '@/components/agents/sections/StartersSection.vue';
import TextSection from '@/components/agents/sections/TextSection.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Brain, Check, Code, Copy, Image as ImageIcon, MessageSquare, Save, Settings, Shield, Type } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

type Llm = Domain.Chat.Data.LlmModelData;

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    settings: Record<string, unknown>;
    defaultSettings: Record<string, unknown>;
    catalog: Llm[];
    selectedModel: string | null;
    embedScriptUrl: string;
    hasPermission: boolean;
}

const props = defineProps<Props>();

const str = (v: unknown, fallback = ''): string => (typeof v === 'string' ? v : fallback);
const num = (v: unknown, fallback = 0): number => (typeof v === 'number' ? v : fallback);

const s = props.settings;
const d = props.defaultSettings;

// Persona is intentionally absent — it lives at the agent level (top-level
// sidebar item) and is shared across every surface. Same menu shape as
// Setup → Widget so the three surface setup pages stay symmetric.
const menuItems = [
    { value: 'info', label: 'Text', icon: Type },
    { value: 'starters', label: 'Starters', icon: MessageSquare },
    { value: 'models', label: 'Model', icon: Brain },
    { value: 'branding', label: 'Branding', icon: ImageIcon },
    { value: 'behavior', label: 'Behavior', icon: Settings },
    { value: 'embed', label: 'Embed', icon: Code },
];

const activeTab = ref('info');
const canEdit = computed(() => props.hasPermission);
const locked = computed(() => !canEdit.value);

// Text fields — saved to the `bar` settings group.
const textForm = useForm({
    welcome_message: str(s.welcome_message, str(d.welcome_message)),
    chat_window_title: str(s.chat_window_title, str(d.chat_window_title)),
    chat_footer_text: str(s.chat_footer_text, str(d.chat_footer_text)),
    composer_placeholder: str(s.composer_placeholder, str(d.composer_placeholder)),
});
const isSavingText = ref(false);
const saveText = () => {
    isSavingText.value = true;
    textForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'bar' }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Text saved'),
        onError: () => toast.error('Could not save text'),
        onFinish: () => (isSavingText.value = false),
    });
};

// Starters live at the agent level (shared endpoint) — same as Widget.
const starters = ref<string[]>([]);
const isSavingStarters = ref(false);
const saveStarters = async () => {
    isSavingStarters.value = true;
    try {
        await axios.put(route('agents.starters.update', { agent: props.agent.external_id }), { text: starters.value });
        toast.success('Starters saved');
    } catch {
        toast.error('Could not save starters');
    } finally {
        isSavingStarters.value = false;
    }
};

// Single-model picker — Bar runs one model. Stored as `model` in the bar
// settings group, separate from the agent's attached LLM catalog.
const modelForm = useForm({ model: props.selectedModel ?? '' });
const isSavingModel = ref(false);
const saveModel = () => {
    isSavingModel.value = true;
    modelForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'bar' }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Model saved'),
        onError: () => toast.error('Could not save model'),
        onFinish: () => (isSavingModel.value = false),
    });
};

// Branding — color theme/shade + avatar/logo. Saved to `bar` group; avatar
// and logo are file uploads handled in dedicated submissions like Widget does.
const brandingForm = useForm({
    color_theme: str(s.color_theme, str(d.color_theme, 'black')),
    color_shade: num(s.color_shade, num(d.color_shade, 700)),
});
const avatarPreview = ref<string | null>(str(s.avatar) || null);
const logoPreview = ref<string | null>(str(s.logo) || null);
const isSavingBranding = ref(false);
const saveBranding = () => {
    isSavingBranding.value = true;
    brandingForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'bar' }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Colors saved'),
        onError: () => toast.error('Could not save colors'),
        onFinish: () => (isSavingBranding.value = false),
    });
};
const onAvatarSelect = (file: File) => uploadImage('avatar', file);
const onLogoSelect = (file: File) => uploadImage('logo', file);
const uploadImage = (key: 'avatar' | 'logo', file: File) => {
    const upload = useForm({ [key]: file });
    upload.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'bar' }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success(`${key === 'avatar' ? 'Avatar' : 'Logo'} updated`);
            router.reload({ only: ['settings'] });
        },
        onError: () => toast.error(`Could not upload ${key}`),
    });
};
const removeAvatar = () => removeImage('avatar');
const removeLogo = () => removeImage('logo');
const removeImage = async (key: 'avatar' | 'logo') => {
    try {
        await axios.delete(route('agents.settings.delete', { agent: props.agent.external_id, group: 'bar' }), {
            data: { key },
        });
        if (key === 'avatar') avatarPreview.value = null;
        else logoPreview.value = null;
        toast.success(`${key === 'avatar' ? 'Avatar' : 'Logo'} removed`);
    } catch {
        toast.error(`Could not remove ${key}`);
    }
};

// Behavior — Bar's two knobs: keyboard trigger + on-page placement.
const behaviorForm = useForm({
    trigger: str(s.trigger, str(d.trigger, 'cmd+k')),
    placement: str(s.placement, str(d.placement, 'top')),
});
const isSavingBehavior = ref(false);
const saveBehavior = () => {
    isSavingBehavior.value = true;
    behaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'bar' }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Behavior saved'),
        onError: () => toast.error('Could not save behavior'),
        onFinish: () => (isSavingBehavior.value = false),
    });
};

const triggerOptions = [
    { value: 'cmd+k', label: '⌘ + K' },
    { value: 'cmd+j', label: '⌘ + J' },
    { value: 'cmd+/', label: '⌘ + /' },
    { value: '/', label: '/' },
    { value: 'shift+/', label: '?' },
];
const placementOptions = [
    { value: 'top', label: 'Top center' },
    { value: 'top-right', label: 'Top right' },
    { value: 'bottom', label: 'Bottom center' },
    { value: 'bottom-right', label: 'Bottom right' },
];

// Embed snippet — same format as the bar preview embed.
const embedSnippet = computed(
    () =>
        `<script id="chatbot-embed-script"\n    src="${props.embedScriptUrl}"\n    data-agent-id="${props.agent.external_id}"\n    data-mode="bar"\n    data-trigger="${behaviorForm.trigger}"\n    data-placement="${behaviorForm.placement}"\n    defer><\/script>`,
);
const copied = ref(false);
const copyEmbed = async () => {
    try {
        await navigator.clipboard.writeText(embedSnippet.value);
        copied.value = true;
        toast.success('Embed snippet copied');
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        toast.error('Copy failed');
    }
};
</script>

<template>
    <AgentLayout :agent="agent" title="Bar Configuration" hide-header no-padding full-height>
        <div class="flex h-full">
            <!-- Left Column - Navigation Menu (mirrors Widget setup) -->
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

            <!-- Right column - Content -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                <Alert v-if="!canEdit" variant="destructive" class="rounded-none border-x-0 border-t-0">
                    <Shield class="h-4 w-4" />
                    <AlertTitle>View Only</AlertTitle>
                    <AlertDescription>You don't have permission to edit Bar settings.</AlertDescription>
                </Alert>

                <div class="flex-1 overflow-y-auto" :class="{ 'pointer-events-none opacity-60 select-none': !canEdit }">
                    <!-- Text -->
                    <div v-if="activeTab === 'info'" class="space-y-6 p-6">
                        <form @submit.prevent="saveText" class="space-y-6">
                            <TextSection
                                v-model:welcome-message="textForm.welcome_message"
                                v-model:chat-window-title="textForm.chat_window_title"
                                v-model:chat-footer-text="textForm.chat_footer_text"
                                v-model:composer-placeholder="textForm.composer_placeholder"
                                :errors="textForm.errors as Record<string, string | undefined>"
                                :disabled="locked"
                            />
                            <div v-if="!locked" class="border-t pt-4">
                                <Button type="submit" :disabled="!canEdit || isSavingText" size="sm">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ isSavingText ? 'Saving...' : 'Save Changes' }}
                                </Button>
                            </div>
                        </form>
                    </div>

                    <!-- Starters -->
                    <div v-if="activeTab === 'starters'" class="space-y-4 p-6">
                        <StartersSection v-model:starters="starters" :disabled="locked" />
                        <div v-if="!locked">
                            <Button type="button" size="sm" :disabled="!canEdit || isSavingStarters" @click="saveStarters">
                                <Save class="mr-2 h-4 w-4" />
                                {{ isSavingStarters ? 'Saving...' : 'Save starters' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Model (single) -->
                    <div v-if="activeTab === 'models'" class="space-y-4 p-6">
                        <div>
                            <h3 class="text-sm font-medium">Active model</h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Bar runs a single model. Hosted Chat is the multi-model surface; Widget and Bar each pick one.
                            </p>
                        </div>
                        <Select v-model="modelForm.model" :disabled="locked">
                            <SelectTrigger class="w-full max-w-md">
                                <SelectValue placeholder="Choose a model" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="llm in catalog" :key="llm.id" :value="llm.id">{{ llm.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="!locked" class="border-t pt-4">
                            <Button type="button" size="sm" :disabled="!canEdit || isSavingModel" @click="saveModel">
                                <Save class="mr-2 h-4 w-4" />
                                {{ isSavingModel ? 'Saving...' : 'Save model' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Branding -->
                    <div v-if="activeTab === 'branding'" class="space-y-6 p-6">
                        <BrandingSection
                            v-model:color-theme="brandingForm.color_theme"
                            v-model:color-shade="brandingForm.color_shade"
                            :avatar-preview="avatarPreview"
                            :logo-preview="logoPreview"
                            :errors="brandingForm.errors as Record<string, string | undefined>"
                            :disabled="locked"
                            @color-apply="saveBranding"
                            @avatar-select="onAvatarSelect"
                            @avatar-remove="removeAvatar"
                            @logo-select="onLogoSelect"
                            @logo-remove="removeLogo"
                        />
                    </div>

                    <!-- Behavior — trigger + placement -->
                    <div v-if="activeTab === 'behavior'" class="space-y-6 p-6">
                        <div>
                            <h3 class="text-sm font-medium">Behaviour</h3>
                            <p class="text-muted-foreground mt-1 text-xs">Keyboard trigger and on-page position of the launcher.</p>
                        </div>
                        <div class="grid max-w-md gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="bar-trigger">Keyboard trigger</Label>
                                <Select v-model="behaviorForm.trigger" :disabled="locked">
                                    <SelectTrigger id="bar-trigger">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="t in triggerOptions" :key="t.value" :value="t.value">{{ t.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label for="bar-placement">Placement</Label>
                                <Select v-model="behaviorForm.placement" :disabled="locked">
                                    <SelectTrigger id="bar-placement">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="p in placementOptions" :key="p.value" :value="p.value">{{ p.label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div v-if="!locked" class="border-t pt-4">
                            <Button type="button" size="sm" :disabled="!canEdit || isSavingBehavior" @click="saveBehavior">
                                <Save class="mr-2 h-4 w-4" />
                                {{ isSavingBehavior ? 'Saving...' : 'Save behaviour' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Embed -->
                    <div v-if="activeTab === 'embed'" class="space-y-4 p-6">
                        <div>
                            <h3 class="text-sm font-medium">Installation Code</h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Copy and paste this into your website's
                                <code class="bg-muted rounded px-1 py-0.5 text-xs">&lt;head&gt;</code> section. The trigger and placement
                                values below come from the Behaviour tab.
                            </p>
                        </div>
                        <div class="bg-muted space-y-3 rounded-lg p-4">
                            <div class="flex items-center justify-end">
                                <Button type="button" variant="outline" size="sm" class="gap-2" @click="copyEmbed">
                                    <component :is="copied ? Check : Copy" class="h-4 w-4" />
                                    {{ copied ? 'Copied' : 'Copy Code' }}
                                </Button>
                            </div>
                            <div class="bg-background rounded border p-3">
                                <pre class="text-foreground overflow-x-auto text-xs"><code v-text="embedSnippet"></code></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AgentLayout>
</template>
