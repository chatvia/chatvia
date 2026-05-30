<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';

import PreviewModal from '@/components/PreviewModal.vue';
import AgentModelSelector from '@/components/agents/AgentModelSelector.vue';
import AgentTemplateBanner from '@/components/agents/AgentTemplateBanner.vue';
import AppearanceTextFields from '@/components/agents/AppearanceTextFields.vue';
import ColorThemePicker from '@/components/agents/ColorThemePicker.vue';
import ImageUploadField from '@/components/agents/ImageUploadField.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    Bot,
    Brain,
    Code,
    Copy,
    Eye,
    Image,
    Info,
    MessageSquare,
    Palette,
    RotateCcw,
    Save,
    Share2,
    Shield,
    Type,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    settings: any;
    default_settings: any;
    embed_settings?: any;
    auth_token?: string | null;
    starters: string[];
    agent_template_id: string | null;
    attached_template: { id: string; name: string } | null;
}

const props = defineProps<Props>();

// Persona lives at the agent level (top-level sidebar item) since it's shared
// across every surface — it's intentionally NOT a sub-tab here.
const menuItems = [
    { value: 'text', label: 'Text', icon: Type },
    { value: 'starters', label: 'Starters', icon: MessageSquare },
    { value: 'models', label: 'Models', icon: Brain },
    { value: 'colors', label: 'Colors', icon: Palette },
    { value: 'branding', label: 'Branding', icon: Image },
    { value: 'embed', label: 'Embed', icon: Code },
    { value: 'share', label: 'Share', icon: Share2 },
    { value: 'security', label: 'Security', icon: Shield },
];

// Active tab
const activeTab = ref('text');

// Form for appearance settings
const appearanceForm = useForm({
    color_theme: props.settings.color_theme || 'black',
    color_shade: props.settings.color_shade || 500,
    welcome_message: props.settings.welcome_message,
    chat_window_title: props.settings.chat_window_title,
    chat_footer_text: props.settings.chat_footer_text,
    composer_placeholder: props.settings.composer_placeholder,
});

// Embed settings form
const embedForm = useForm({
    remove_sidebar: props.embed_settings?.remove_sidebar ?? false,
    hide_sidebar_on_page_load: props.embed_settings?.hide_sidebar_on_page_load ?? false,
    enable_theme_switcher: props.embed_settings?.enable_theme_switcher ?? true,
    default_theme: props.embed_settings?.default_theme ?? 'auto',
    enable_chat_expand: props.embed_settings?.enable_chat_expand ?? true,
});

// Security form
const securityForm = useForm({
    is_public: props.agent.is_public,
});

// Iframe options for display
const iframeOptions = reactive({
    width: '100%',
    height: '600px',
    border: 'none',
    allowFullscreen: true,
    loading: true,
    title: `Chat with ${props.agent.name}`,
});

// Computed properties for text fields
const hasUnsavedChanges = computed(() => {
    return (
        appearanceForm.welcome_message !== props.settings.welcome_message ||
        appearanceForm.chat_window_title !== props.settings.chat_window_title ||
        appearanceForm.chat_footer_text !== props.settings.chat_footer_text ||
        appearanceForm.composer_placeholder !== props.settings.composer_placeholder
    );
});

// States
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const showPreviewModal = ref(false);
const showSecurityConfirmDialog = ref(false);
const avatarPreview = ref<string | null>(props.settings.avatar || null);
const logoPreview = ref<string | null>(props.settings.logo || null);
const embedKey = ref(0);
const isPreviewResizing = ref(false);
const starters = ref<string[]>(props.starters || []);
const isSavingStarters = ref(false);

// When a template is attached the agent reads its config from the template, so
// the per-agent setup fields are locked until the user detaches.
const locked = computed(() => props.agent_template_id !== null);

const updateAppearance = () => {
    isSubmitting.value = true;
    showSuccessMessage.value = false;

    const textForm = useForm({
        welcome_message: appearanceForm.welcome_message,
        chat_window_title: appearanceForm.chat_window_title,
        chat_footer_text: appearanceForm.chat_footer_text,
        composer_placeholder: appearanceForm.composer_placeholder,
    });

    textForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            showSuccessMessage.value = true;
            embedKey.value += 1;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const onAvatarSelect = (file: File) => {
    const reader = new FileReader();
    reader.onload = (e) => {
        if (e.target && typeof e.target.result === 'string') {
            avatarPreview.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);

    const uploadForm = useForm({ avatar: file });

    uploadForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success('Avatar updated successfully');
            embedKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update avatar');
        },
    });
};

const removeAvatar = () => {
    router.delete(
        route('agents.settings.delete', {
            agent: props.agent.external_id,
            group: 'appearance',
            key: 'avatar',
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                avatarPreview.value = null;
                toast.success('Avatar removed successfully');
                embedKey.value += 1;
            },
            onError: () => {
                toast.error('Failed to remove avatar');
            },
        },
    );
};

const onLogoSelect = (file: File) => {
    const reader = new FileReader();
    reader.onload = (e) => {
        if (e.target && typeof e.target.result === 'string') {
            logoPreview.value = e.target.result;
        }
    };
    reader.readAsDataURL(file);

    const uploadForm = useForm({ logo: file });

    uploadForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast.success('Logo updated successfully');
            embedKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update logo');
        },
    });
};

const removeLogo = () => {
    router.delete(
        route('agents.settings.delete', {
            agent: props.agent.external_id,
            group: 'appearance',
            key: 'logo',
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                logoPreview.value = null;
                toast.success('Logo removed successfully');
                embedKey.value += 1;
            },
            onError: () => {
                toast.error('Failed to remove logo');
            },
        },
    );
};

const updateSetting = (key: string, value: any) => {
    (embedForm as any)[key] = value;

    const dataToSend: any = { [key]: value };

    if (key === 'remove_sidebar' && value === true) {
        (embedForm as any).hide_sidebar_on_page_load = false;
        dataToSend.hide_sidebar_on_page_load = false;
    }

    router.post(
        route('agents.settings.update', {
            agent: props.agent.external_id,
            group: 'embed',
        }),
        dataToSend,
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Setting updated successfully');
                embedKey.value += 1;
            },
            onError: () => {
                toast.error('Failed to update setting');
            },
        },
    );
};

const buildIframeUrl = (baseUrl: string) => {
    if (props.auth_token) {
        const separator = baseUrl.includes('?') ? '&' : '?';
        return `${baseUrl}${separator}auth_token=${props.auth_token}`;
    }
    return baseUrl;
};

const embedCode = computed(() => {
    const attributes = [
        `src="${props.agent.chat_embed_url}"`,
        `width="${iframeOptions.width}"`,
        `height="${iframeOptions.height}"`,
        `title="${iframeOptions.title}"`,
        `frameborder="0"`,
    ];

    if (iframeOptions.border !== 'none') {
        attributes.push(`style="border: ${iframeOptions.border}"`);
    }

    if (iframeOptions.allowFullscreen === true) {
        attributes.push('allowfullscreen');
    }

    if (iframeOptions.loading === true) {
        attributes.push('loading="lazy"');
    }

    return `<iframe ${attributes.join(' ')}></iframe>`;
});

const copyEmbedCode = async () => {
    try {
        await navigator.clipboard.writeText(embedCode.value);
        toast.success('Embed code copied to clipboard!');
    } catch (error) {
        console.error('Failed to copy embed code:', error);
        toast.error('Failed to copy embed code. Please try again.');
    }
};

const setPresetSize = (preset: string) => {
    switch (preset) {
        case 'full':
            iframeOptions.width = '100%';
            iframeOptions.height = '100vh';
            break;
        case 'large':
            iframeOptions.width = '800px';
            iframeOptions.height = '600px';
            break;
        case 'medium':
            iframeOptions.width = '600px';
            iframeOptions.height = '500px';
            break;
        case 'small':
            iframeOptions.width = '400px';
            iframeOptions.height = '400px';
            break;
    }
};

const refreshPreview = () => {
    embedKey.value += 1;
};

const copyAgentUrl = async () => {
    try {
        await navigator.clipboard.writeText(props.agent.permanent_url || props.agent.url);
        toast.success('Agent URL copied to clipboard!');
    } catch (error) {
        console.error('Failed to copy URL:', error);
        toast.error('Failed to copy URL. Please try again.');
    }
};

const updateSecuritySettings = () => {
    securityForm.patch(route('agents.security.update', props.agent.external_id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Security settings updated');
        },
        onError: () => {
            toast.error('Failed to update security settings');
            securityForm.is_public = !securityForm.is_public;
        },
    });
};

const confirmMakePublic = () => {
    showSecurityConfirmDialog.value = false;
    updateSecuritySettings();
};

const cancelMakePublic = () => {
    showSecurityConfirmDialog.value = false;
    securityForm.is_public = false;
};

watch(
    () => securityForm.is_public,
    (newValue, oldValue) => {
        if (oldValue !== undefined && newValue !== oldValue) {
            if (newValue === true) {
                showSecurityConfirmDialog.value = true;
            } else {
                updateSecuritySettings();
            }
        }
    },
);

const saveColorSettings = () => {
    const colorForm = useForm({
        color_theme: appearanceForm.color_theme,
        color_shade: appearanceForm.color_shade,
    });

    colorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Color updated successfully');
            embedKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update color');
        },
    });
};

const saveStarters = () => {
    isSavingStarters.value = true;
    axios
        .put(route('agents.starters.update', { agent: props.agent.external_id }), { text: starters.value })
        .then(() => {
            toast.success('Starters saved');
            embedKey.value += 1;
        })
        .catch(() => {
            toast.error('Failed to save starters');
        })
        .finally(() => {
            isSavingStarters.value = false;
        });
};
</script>

<template>
    <AgentLayout :agent="agent" title="Embed" hide-header no-padding full-height>
        <div class="flex h-full">
            <!-- Left Column - Navigation Menu -->
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

            <!-- Resizable split: configuration content | live preview -->
            <ResizablePanelGroup direction="horizontal" auto-save-id="embed-setup-preview" class="min-w-0 flex-1">
                <!-- Configuration Content -->
                <ResizablePanel :default-size="62" :min-size="35">
                    <div class="flex h-full flex-col overflow-hidden border-r">
                        <div class="flex-1 overflow-y-auto">
                            <div v-if="['text', 'persona', 'starters', 'models', 'colors', 'branding'].includes(activeTab)" class="px-6 pt-6">
                                <AgentTemplateBanner
                                    :agent-id="agent.external_id"
                                    :attached-template="attached_template"
                                />
                            </div>

                            <!-- Text Panel -->
                            <div v-if="activeTab === 'text'" class="space-y-6 p-6">
                                <form @submit.prevent="updateAppearance" class="space-y-6">
                                    <AppearanceTextFields
                                        v-model:welcome-message="appearanceForm.welcome_message"
                                        v-model:chat-window-title="appearanceForm.chat_window_title"
                                        v-model:chat-footer-text="appearanceForm.chat_footer_text"
                                        v-model:composer-placeholder="appearanceForm.composer_placeholder"
                                        :errors="appearanceForm.errors"
                                        :disabled="locked"
                                    />

                                    <div v-if="!locked" class="border-t pt-4">
                                        <Button type="submit" :disabled="isSubmitting || !hasUnsavedChanges" size="sm">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                        </Button>
                                        <p v-if="showSuccessMessage" class="mt-2 text-sm text-green-600">Settings saved successfully</p>
                                    </div>
                                </form>
                            </div>

                            <!-- Starters Panel -->
                            <div v-if="activeTab === 'starters'" class="space-y-4 p-6">
                                <div>
                                    <h3 class="text-sm font-medium">Conversation Starters</h3>
                                    <p class="text-muted-foreground mt-1 text-xs">
                                        Suggest questions to help users start conversations. Press Enter to add each one.
                                    </p>
                                </div>

                                <TagsInput v-model="starters" :disabled="locked" class="w-full">
                                    <TagsInputItem v-for="(starter, index) in starters" :key="index" :value="starter">
                                        <TagsInputItemText />
                                        <TagsInputItemDelete />
                                    </TagsInputItem>
                                    <TagsInputInput placeholder="E.g., How can I track my order?" />
                                </TagsInput>

                                <div v-if="!locked" class="border-t pt-3">
                                    <Button type="button" size="sm" :disabled="isSavingStarters" @click="saveStarters">
                                        <Save class="mr-2 h-4 w-4" />
                                        {{ isSavingStarters ? 'Saving...' : 'Save starters' }}
                                    </Button>
                                </div>

                                <Alert>
                                    <Info class="h-4 w-4" />
                                    <AlertDescription>
                                        Good starter messages are specific questions that showcase your agent's capabilities
                                    </AlertDescription>
                                </Alert>
                            </div>

                            <!-- Models Panel -->
                            <div v-if="activeTab === 'models'" class="p-6">
                                <AgentModelSelector
                                    :agent-id="agent.external_id"
                                    :multiple="true"
                                    :locked="locked"
                                    description="Select one or more AI models for your knowledge base. Users can switch between selected models when chatting."
                                    @saved="refreshPreview"
                                />
                            </div>

                            <!-- Colors Panel -->
                            <div v-if="activeTab === 'colors'" class="space-y-2 p-6">
                                <Label>Color Theme</Label>
                                <p class="text-muted-foreground mb-3 text-xs">Click the color swatch to choose your primary color</p>

                                <ColorThemePicker
                                    v-model:theme="appearanceForm.color_theme"
                                    v-model:shade="appearanceForm.color_shade"
                                    :disabled="locked"
                                    @apply="saveColorSettings"
                                />

                                <p v-if="appearanceForm.errors.color_theme" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.color_theme }}
                                </p>
                            </div>

                            <!-- Branding Panel -->
                            <div v-if="activeTab === 'branding'" class="space-y-6 p-6">
                                <ImageUploadField
                                    label="Agent Avatar"
                                    description="Upload an avatar for your agent (256x256px recommended)"
                                    upload-label="Upload Avatar"
                                    icon="user"
                                    :preview="avatarPreview"
                                    :disabled="locked"
                                    @select="onAvatarSelect"
                                    @remove="removeAvatar"
                                />

                                <ImageUploadField
                                    label="Logo Image"
                                    description="Upload a logo for your agent (256x256px recommended)"
                                    upload-label="Upload Logo"
                                    icon="image"
                                    :preview="logoPreview"
                                    :disabled="locked"
                                    @select="onLogoSelect"
                                    @remove="removeLogo"
                                />
                            </div>

                            <!-- Embed Panel -->
                            <div v-if="activeTab === 'embed'" class="space-y-4 p-6">
                                <div class="space-y-3">
                                    <Label class="text-sm font-medium">Embed Configuration</Label>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <Label for="embed_width" class="text-xs">Width</Label>
                                            <Input id="embed_width" v-model="iframeOptions.width" type="text" placeholder="100%" class="text-sm" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="embed_height" class="text-xs">Height</Label>
                                            <Input id="embed_height" v-model="iframeOptions.height" type="text" placeholder="600px" class="text-sm" />
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <Button type="button" variant="outline" size="sm" @click="setPresetSize('full')" class="text-xs">
                                            Full Width
                                        </Button>
                                        <Button type="button" variant="outline" size="sm" @click="setPresetSize('large')" class="text-xs">
                                            Large
                                        </Button>
                                    </div>
                                </div>

                                <div class="bg-muted rounded-lg p-4">
                                    <div class="mb-3 flex items-center justify-between">
                                        <p class="text-muted-foreground text-sm">HTML Embed Code</p>
                                        <Button type="button" variant="outline" size="sm" @click="copyEmbedCode" class="gap-2">
                                            <Copy class="h-4 w-4" />
                                            Copy
                                        </Button>
                                    </div>
                                    <div class="bg-background rounded border p-3">
                                        <pre
                                            class="text-foreground overflow-x-auto text-xs whitespace-pre-wrap"
                                        ><code v-text="embedCode"></code></pre>
                                    </div>
                                </div>

                                <div class="space-y-4 border-t pt-4">
                                    <h4 class="text-sm font-medium">Interface Options</h4>

                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <Label for="remove_sidebar" class="text-sm">Remove sidebar</Label>
                                            <Switch
                                                id="remove_sidebar"
                                                :model-value="embedForm.remove_sidebar"
                                                @update:model-value="(value: any) => updateSetting('remove_sidebar', value)"
                                            />
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <Label for="enable_chat_expand" class="text-sm">Enable chat expand</Label>
                                            <Switch
                                                id="enable_chat_expand"
                                                :model-value="embedForm.enable_chat_expand"
                                                @update:model-value="(value: any) => updateSetting('enable_chat_expand', value)"
                                            />
                                        </div>

                                        <div class="flex items-center justify-between">
                                            <Label for="enable_theme_switcher" class="text-sm">Enable theme switcher</Label>
                                            <Switch
                                                id="enable_theme_switcher"
                                                :model-value="embedForm.enable_theme_switcher"
                                                @update:model-value="(value: any) => updateSetting('enable_theme_switcher', value)"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="default_theme" class="text-sm">Default theme</Label>
                                            <Select
                                                v-model="embedForm.default_theme"
                                                @update:model-value="(value: any) => updateSetting('default_theme', value)"
                                            >
                                                <SelectTrigger class="w-full">
                                                    <SelectValue placeholder="Select default theme" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="auto">Auto (System)</SelectItem>
                                                    <SelectItem value="light">Light</SelectItem>
                                                    <SelectItem value="dark">Dark</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Share Panel -->
                            <div v-if="activeTab === 'share'" class="space-y-4 p-6">
                                <div>
                                    <Label class="text-base">Agent URL</Label>
                                    <p class="text-muted-foreground mt-1 mb-3 text-sm">Share this URL to give others access to your agent</p>
                                </div>

                                <div class="bg-muted rounded-lg p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <code class="text-foreground text-sm break-all">
                                            {{ agent.permanent_url || agent.url }}
                                        </code>
                                        <Button type="button" variant="outline" size="sm" @click="copyAgentUrl" class="shrink-0 gap-2">
                                            <Copy class="h-4 w-4" />
                                            Copy
                                        </Button>
                                    </div>
                                </div>

                                <Alert>
                                    <Info class="h-4 w-4" />
                                    <AlertDescription>
                                        Anyone with this URL can access your agent. Make sure to set the appropriate security settings.
                                    </AlertDescription>
                                </Alert>
                            </div>

                            <!-- Security Panel -->
                            <div v-if="activeTab === 'security'" class="space-y-4 p-6">
                                <div class="flex items-center justify-between space-x-2">
                                    <div class="flex-1">
                                        <Label class="text-base" for="is_public">Make this agent public</Label>
                                        <p class="text-muted-foreground mt-1 text-sm">Public agents can be accessed by anyone with the link</p>
                                        <p v-if="locked" class="text-muted-foreground mt-1 text-sm italic">
                                            Controlled by the attached template. Detach to override per agent.
                                        </p>
                                    </div>
                                    <Switch id="is_public" v-model="securityForm.is_public" :disabled="locked" />
                                </div>
                            </div>
                        </div>
                    </div>
                </ResizablePanel>

                <ResizableHandle with-handle @dragging="isPreviewResizing = $event" />

                <!-- Live Preview -->
                <ResizablePanel :default-size="38" :min-size="25">
                    <div class="bg-background flex h-full flex-col">
                        <!-- Preview Header -->
                        <div class="flex flex-shrink-0 items-center justify-between border-b px-4 py-3">
                            <span class="text-muted-foreground text-sm font-medium">Live Preview</span>
                            <div class="flex items-center gap-1">
                                <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5" @click="refreshPreview">
                                    <RotateCcw class="h-3.5 w-3.5" />
                                    <span class="text-xs">Refresh</span>
                                </Button>
                                <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5" @click="showPreviewModal = true">
                                    <Eye class="h-3.5 w-3.5" />
                                    <span class="text-xs">Preview</span>
                                </Button>
                            </div>
                        </div>

                        <!-- Preview Frame -->
                        <div class="flex-1 overflow-hidden">
                            <iframe
                                :key="embedKey"
                                :src="buildIframeUrl(agent.chat_embed_url)"
                                class="h-full w-full border-0"
                                :class="{ 'pointer-events-none': isPreviewResizing }"
                                title="Chat Preview"
                            />
                        </div>
                    </div>
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>

        <!-- Preview Modal -->
        <PreviewModal
            v-model="showPreviewModal"
            preview-type="embed"
            :preview-url="buildIframeUrl(agent.chat_embed_url)"
            iframe-title="Chat Preview"
        />

        <!-- Security Confirmation Dialog -->
        <Dialog v-model:open="showSecurityConfirmDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                        Make Agent Public?
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to make this agent public? Anyone with the link will be able to access and interact with this agent.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="cancelMakePublic"> Cancel </Button>
                    <Button @click="confirmMakePublic"> Make Public </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
