<script lang="ts" setup>
import AgentLayout from '@/layouts/AgentLayout.vue';

import PreviewModal from '@/components/PreviewModal.vue';
import AgentModelSelector from '@/components/agents/AgentModelSelector.vue';
import AgentPersonaEditor from '@/components/agents/AgentPersonaEditor.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
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
    PlusCircle,
    RotateCcw,
    Save,
    Share2,
    Shield,
    Trash2,
    Type,
    Upload,
    User,
} from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    settings: any;
    default_settings: any;
    embed_settings?: any;
    auth_token?: string | null;
    starters: Array<{ id: number; text: string }>;
}

const props = defineProps<Props>();

// Menu items for navigation
const menuItems = [
    { value: 'text', label: 'Text', icon: Type },
    { value: 'persona', label: 'Persona', icon: Bot },
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
const showColorPicker = ref(false);
const showPreviewModal = ref(false);
const showSecurityConfirmDialog = ref(false);
const avatarPreview = ref<string | null>(props.settings.avatar || null);
const logoPreview = ref<string | null>(props.settings.logo || null);
const avatarInputRef = ref<HTMLInputElement | null>(null);
const logoInputRef = ref<HTMLInputElement | null>(null);
const embedKey = ref(0);
const showAddStarterDialog = ref(false);
const starterMessageForm = ref({ text: '' });
const starters = ref(props.starters || []);

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

const triggerAvatarInput = () => {
    if (avatarInputRef.value) {
        avatarInputRef.value.click();
    }
};

const handleAvatarUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

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
                if (avatarInputRef.value) {
                    avatarInputRef.value.value = '';
                }
                toast.success('Avatar removed successfully');
                embedKey.value += 1;
            },
            onError: () => {
                toast.error('Failed to remove avatar');
            },
        },
    );
};

const triggerLogoInput = () => {
    if (logoInputRef.value) {
        logoInputRef.value.click();
    }
};

const handleLogoUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

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
                if (logoInputRef.value) {
                    logoInputRef.value.value = '';
                }
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

const addStarterMessage = () => {
    axios
        .post(route('agents.starters.store', props.agent.external_id), { text: starterMessageForm.value.text })
        .then((response: any) => {
            showAddStarterDialog.value = false;
            starters.value.push({
                id: response.data.id,
                text: response.data.text,
            });
            starterMessageForm.value.text = '';
            toast.success('Starter message added');
            embedKey.value += 1;
        })
        .catch(() => {
            toast.error('Failed to add starter message');
        });
};

const removeStarter = (id: number) => {
    axios
        .delete(route('agents.starters.destroy', { agent: props.agent.external_id, id }))
        .then(() => {
            starters.value = starters.value.filter((starter) => starter.id !== id);
            toast.success('Starter message removed');
            embedKey.value += 1;
        })
        .catch(() => {
            toast.error('Failed to remove starter message');
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

            <!-- Middle Column - Configuration Content -->
            <div class="flex h-full flex-1 flex-col overflow-hidden border-r">
                <div class="flex-1 overflow-y-auto">
                    <!-- Text Panel -->
                    <div v-if="activeTab === 'text'" class="space-y-6 p-6">
                        <form @submit.prevent="updateAppearance" class="space-y-6">
                            <div class="space-y-2">
                                <Label for="welcome_message">Welcome Message</Label>
                                <Textarea
                                    id="welcome_message"
                                    v-model="appearanceForm.welcome_message"
                                    placeholder="Hi there! How can I help you today?"
                                    rows="3"
                                />
                                <p v-if="appearanceForm.errors.welcome_message" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.welcome_message }}
                                </p>
                                <p class="text-muted-foreground text-xs">First message shown to users</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="chat_window_title">Chat Window Title</Label>
                                <Input id="chat_window_title" v-model="appearanceForm.chat_window_title" placeholder="Chat with our AI Assistant" />
                                <p v-if="appearanceForm.errors.chat_window_title" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.chat_window_title }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="chat_footer_text">Chat Footer Text</Label>
                                <Input
                                    id="chat_footer_text"
                                    v-model="appearanceForm.chat_footer_text"
                                    placeholder="Consider checking important information"
                                />
                                <p v-if="appearanceForm.errors.chat_footer_text" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.chat_footer_text }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="composer_placeholder">Composer Placeholder</Label>
                                <Input id="composer_placeholder" v-model="appearanceForm.composer_placeholder" placeholder="Ask anything..." />
                                <p v-if="appearanceForm.errors.composer_placeholder" class="text-destructive text-sm">
                                    {{ appearanceForm.errors.composer_placeholder }}
                                </p>
                                <p class="text-muted-foreground text-xs">Placeholder text in the chat input field</p>
                            </div>

                            <div class="border-t pt-4">
                                <Button type="submit" :disabled="isSubmitting || !hasUnsavedChanges" size="sm">
                                    <Save class="mr-2 h-4 w-4" />
                                    {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                </Button>
                                <p v-if="showSuccessMessage" class="mt-2 text-sm text-green-600">Settings saved successfully</p>
                            </div>
                        </form>
                    </div>

                    <!-- Persona Panel -->
                    <div v-if="activeTab === 'persona'" class="p-6">
                        <AgentPersonaEditor :agent-id="agent.external_id" :initial-instructions="agent.system_instructions" @saved="refreshPreview" />
                    </div>

                    <!-- Starters Panel -->
                    <div v-if="activeTab === 'starters'" class="space-y-4 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium">Conversation Starters</h3>
                                <p class="text-muted-foreground mt-1 text-xs">Suggest questions to help users start conversations</p>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="showAddStarterDialog = true">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Add Starter
                            </Button>
                        </div>

                        <div class="rounded-md border">
                            <div v-if="starters.length === 0" class="text-muted-foreground p-4 text-center text-sm">No starter messages yet</div>
                            <div v-for="starter in starters" :key="starter.id" class="flex items-center justify-between border-b p-3 last:border-0">
                                <div class="flex items-center gap-3">
                                    <MessageSquare class="text-muted-foreground h-4 w-4" />
                                    <div class="text-sm">{{ starter.text }}</div>
                                </div>
                                <Button
                                    type="button"
                                    class="text-destructive hover:text-destructive/80 h-8 w-8 p-0"
                                    size="sm"
                                    variant="ghost"
                                    @click="removeStarter(starter.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
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
                            description="Select one or more AI models for your knowledge base. Users can switch between selected models when chatting."
                            @saved="refreshPreview"
                        />
                    </div>

                    <!-- Colors Panel -->
                    <div v-if="activeTab === 'colors'" class="space-y-2 p-6">
                        <Label>Color Theme</Label>
                        <p class="text-muted-foreground mb-3 text-xs">Click the color swatch to choose your primary color</p>

                        <div class="relative inline-block cursor-pointer" @click="showColorPicker = true">
                            <div
                                class="border-border h-20 w-20 rounded-lg border-2 shadow-sm transition-all duration-200 hover:scale-105 hover:shadow-md"
                                :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"
                            >
                                <div class="flex h-full items-center justify-center">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                        <div
                                            class="h-6 w-6 rounded-full"
                                            :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p v-if="appearanceForm.errors.color_theme" class="text-destructive text-sm">
                            {{ appearanceForm.errors.color_theme }}
                        </p>
                    </div>

                    <!-- Branding Panel -->
                    <div v-if="activeTab === 'branding'" class="space-y-6 p-6">
                        <div class="space-y-2">
                            <Label>Agent Avatar</Label>
                            <p class="text-muted-foreground mb-3 text-xs">Upload an avatar for your agent (256x256px recommended)</p>

                            <div class="flex items-center gap-4">
                                <div class="bg-muted h-20 w-20 overflow-hidden rounded-full border">
                                    <img v-if="avatarPreview" :src="avatarPreview" alt="Agent avatar" class="h-full w-full object-cover" />
                                    <div v-else class="text-muted-foreground flex h-full w-full items-center justify-center">
                                        <User class="h-10 w-10" />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <input ref="avatarInputRef" accept="image/*" class="hidden" type="file" @change="handleAvatarUpload" />
                                    <Button type="button" variant="outline" size="sm" @click="triggerAvatarInput">
                                        <Upload class="mr-2 h-4 w-4" />
                                        Upload Avatar
                                    </Button>
                                    <Button
                                        v-if="avatarPreview"
                                        class="text-destructive hover:text-destructive/80"
                                        size="sm"
                                        type="button"
                                        variant="ghost"
                                        @click="removeAvatar"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Logo Image</Label>
                            <p class="text-muted-foreground mb-3 text-xs">Upload a logo for your agent (256x256px recommended)</p>

                            <div class="flex items-center gap-4">
                                <div class="bg-muted h-20 w-20 overflow-hidden rounded-full border">
                                    <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="h-full w-full object-cover" />
                                    <div v-else class="text-muted-foreground flex h-full w-full items-center justify-center">
                                        <Image class="h-10 w-10" />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2">
                                    <input ref="logoInputRef" accept="image/*" class="hidden" type="file" @change="handleLogoUpload" />
                                    <Button type="button" variant="outline" size="sm" @click="triggerLogoInput">
                                        <Upload class="mr-2 h-4 w-4" />
                                        Upload Logo
                                    </Button>
                                    <Button
                                        v-if="logoPreview"
                                        class="text-destructive hover:text-destructive/80"
                                        size="sm"
                                        type="button"
                                        variant="ghost"
                                        @click="removeLogo"
                                    >
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        </div>
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
                                <Button type="button" variant="outline" size="sm" @click="setPresetSize('full')" class="text-xs"> Full Width </Button>
                                <Button type="button" variant="outline" size="sm" @click="setPresetSize('large')" class="text-xs"> Large </Button>
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
                                <pre class="text-foreground overflow-x-auto text-xs whitespace-pre-wrap"><code v-text="embedCode"></code></pre>
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
                            </div>
                            <Switch id="is_public" v-model="securityForm.is_public" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column - Live Preview -->
            <div class="bg-background flex h-full w-[420px] flex-shrink-0 flex-col">
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
                    <iframe :key="embedKey" :src="buildIframeUrl(agent.chat_embed_url)" class="h-full w-full border-0" title="Chat Preview" />
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <PreviewModal
            v-model="showPreviewModal"
            preview-type="embed"
            :preview-url="buildIframeUrl(agent.chat_embed_url)"
            iframe-title="Chat Preview"
        />

        <!-- Color Picker Dialog -->
        <Dialog v-model:open="showColorPicker">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Palette class="h-5 w-5" />
                        Choose Color Theme
                    </DialogTitle>
                    <DialogDescription> Select a color and shade for your agent's primary theme </DialogDescription>
                </DialogHeader>

                <div class="space-y-6">
                    <div class="grid grid-cols-8 gap-2">
                        <div
                            v-for="theme in [
                                'black',
                                'red',
                                'orange',
                                'amber',
                                'yellow',
                                'lime',
                                'green',
                                'emerald',
                                'teal',
                                'cyan',
                                'sky',
                                'blue',
                                'indigo',
                                'violet',
                                'purple',
                                'fuchsia',
                                'pink',
                                'rose',
                                'slate',
                                'gray',
                                'zinc',
                                'neutral',
                                'stone',
                            ]"
                            :key="theme"
                            class="group relative cursor-pointer"
                            @click="appearanceForm.color_theme = theme"
                        >
                            <div
                                class="h-12 w-full rounded-lg border-2 shadow-sm transition-all duration-200"
                                :class="{
                                    'border-primary ring-primary/20 ring-2': appearanceForm.color_theme === theme,
                                    'border-border hover:border-primary/50 hover:shadow-md': appearanceForm.color_theme !== theme,
                                }"
                                :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"
                            >
                                <div class="flex h-full items-center justify-center">
                                    <div
                                        v-if="appearanceForm.color_theme === theme"
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-white shadow-lg"
                                    >
                                        <div
                                            class="h-3 w-3 rounded-full"
                                            :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                            <p
                                class="mt-1 text-center text-xs font-medium capitalize"
                                :class="{
                                    'text-primary': appearanceForm.color_theme === theme,
                                    'text-muted-foreground': appearanceForm.color_theme !== theme,
                                }"
                            >
                                {{ theme }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label class="text-sm">Color Intensity</Label>
                        <div class="px-1">
                            <input
                                type="range"
                                min="50"
                                max="950"
                                step="50"
                                v-model="appearanceForm.color_shade"
                                class="slider h-3 w-full cursor-pointer appearance-none rounded-lg bg-gray-200"
                                :style="{
                                    background: `linear-gradient(to right, var(--${appearanceForm.color_theme}-50), var(--${appearanceForm.color_theme}-950))`,
                                }"
                            />
                        </div>
                        <div class="text-muted-foreground flex justify-between text-xs">
                            <span>Light</span>
                            <span class="font-medium">{{ appearanceForm.color_shade }}</span>
                            <span>Dark</span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        @click="
                            () => {
                                saveColorSettings();
                                showColorPicker = false;
                            }
                        "
                    >
                        Apply & Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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

        <!-- Add Starter Dialog -->
        <Dialog v-model:open="showAddStarterDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Starter Message</DialogTitle>
                    <DialogDescription> Create a new conversation starter for your users </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label class="mb-2" for="starter-message">Message Text</Label>
                    <Textarea id="starter-message" v-model="starterMessageForm.text" placeholder="E.g., How can I track my order?" rows="3" />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddStarterDialog = false"> Cancel </Button>
                    <Button :disabled="!starterMessageForm.text.trim()" @click="addStarterMessage">
                        <PlusCircle class="mr-2 h-4 w-4" />
                        Add Message
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>
