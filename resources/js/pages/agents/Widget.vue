<template>
    <AgentLayout :agent="agent" title="Widget Configuration">
        <template #header-actions>
            <HeaderActions
                :is-submitting="isSubmitting"
                :has-unsaved-changes="hasUnsavedChanges"
                :show-success-message="showSuccessMessage"
                success-message="Widget settings saved successfully"
                :show-error-message="hasFormErrors"
                error-message="Please fix errors below"
                save-label="Save"
                :save-icon="Save"
                form-id="widget-settings-form"
                :secondary-actions="[
                    {
                        id: 'restore-defaults',
                        label: 'Restore Defaults',
                        icon: RotateCcw,
                        onClick: restoreDefaults
                    }
                ]"
            />
        </template>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column - Widget Configuration -->
            <div class="space-y-6 order-1 lg:order-none">
                <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Globe class="h-5 w-5" />
                        Widget Configuration
                    </CardTitle>
                    <CardDescription>
                        Configure your agent as an embeddable chaWt widget for your website.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form id="widget-settings-form" @submit.prevent="updateWidgetSettings" class="space-y-8">
                        <!-- Title, Welcome ChatMessage, and Widget Color -->
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_1fr_auto] gap-6">
                            <!-- Chat Title -->
                            <div class="space-y-2">
                                <Label for="chat_window_title">Title</Label>
                                <Input
                                    id="chat_window_title"
                                    v-model="appearanceForm.chat_window_title"
                                    placeholder="Chat with us"
                                />
                                <p v-if="appearanceForm.errors.chat_window_title" class="text-sm text-destructive">
                                    {{ appearanceForm.errors.chat_window_title }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    This title appears at the top of the chat window
                                </p>
                            </div>

                            <!-- Welcome ChatMessage -->
                            <div class="space-y-2">
                                <Label for="welcome_message">Welcome Message</Label>
                                <Input
                                    id="welcome_message"
                                    v-model="appearanceForm.welcome_message"
                                    placeholder="Hello! How can I help you today?"
                                />
                                <p v-if="appearanceForm.errors.welcome_message" class="text-sm text-destructive">
                                    {{ appearanceForm.errors.welcome_message }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    This message will be shown when users first open the widget
                                </p>
                            </div>

                            <!-- Widget Color -->
                            <div class="space-y-2">
                                <Label>Color</Label>
                                <div class="flex items-center gap-3">
                                    <!-- Selected Color Preview -->
                                    <div class="relative cursor-pointer" @click="showColorPicker = true">
                                        <div
                                            class="h-10 w-10 rounded-lg border-2 border-border shadow-sm transition-all duration-200 hover:shadow-md hover:scale-105"
                                            :style="{
                                                backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})`
                                            }"
                                        >
                                            <div class="flex h-full items-center justify-center">
                                                <div class="h-6 w-6 rounded-full bg-white/90 shadow-sm flex items-center justify-center">
                                                    <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="appearanceForm.errors.color_theme" class="text-sm text-destructive">
                                    {{ appearanceForm.errors.color_theme }}
                                </p>
                            </div>
                        </div>
                        <Alert>
                            <AlertDescription class="font-bold">
                                Position, Window Size and Chat Bubbles are only visible in the preview mode.
                            </AlertDescription>
                        </Alert>

                        <!-- Widget Settings -->
                        <div class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <Label for="position">Position</Label>
                                    <Select v-model="widgetForm.position">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select position" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="bottom-right">Bottom Right</SelectItem>
                                            <SelectItem value="bottom-left">Bottom Left</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div class="space-y-2">
                                    <Label for="window_size">Window Size</Label>
                                    <Select v-model="widgetForm.window_size">
                                        <SelectTrigger class="w-full">
                                            <SelectValue placeholder="Select size" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="small">Small</SelectItem>
                                            <SelectItem value="medium">Medium</SelectItem>
                                            <SelectItem value="large">Large</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        <!-- Behavior Settings -->
                        <div class="space-y-4">
                            <div class="space-y-4">
                                <div class="flex items-center gap-2">
                                    <Switch id="show_on_load" :model-value="widgetForm.show_on_load" @update:model-value="(value: any) => widgetForm.show_on_load = value" />
                                    <Label for="show_on_load">Show widget automatically when page loads</Label>
                                </div>

                                <div v-if="widgetForm.show_on_load" class="ml-6 space-y-1">
                                    <Label for="show_on_load_delay">Delay (milliseconds)</Label>
                                    <Input id="show_on_load_delay" v-model.number="widgetForm.show_on_load_delay" type="number" min="0" step="100" placeholder="500" />
                                </div>
                            </div>
                        </div>

                        <!-- Bubbles -->
                        <div class="space-y-2">
                            <Label>Chat Bubbles</Label>
                            <p class="text-xs text-muted-foreground">Add quick message bubbles that users can click to start conversations</p>

                            <TagsInput v-model="widgetForm.bubbles" class="w-full" delimiter="Enter">
                                <TagsInputItem v-for="(bubble, index) in widgetForm.bubbles" :key="index" :value="bubble">
                                    <TagsInputItemText />
                                    <TagsInputItemDelete />
                                </TagsInputItem>
                                <TagsInputInput placeholder="Enter a conversation starter (e.g., How can I help you?)" />
                            </TagsInput>

                            <p v-if="widgetForm.errors.bubbles" class="text-sm text-destructive">
                                {{ widgetForm.errors.bubbles }}
                            </p>

                            <p class="text-xs text-muted-foreground">
                                These appear as clickable bubbles to help users start conversations quickly
                            </p>
                        </div>

                        <!-- Embed Code Preview -->
                        <div class="space-y-6">
                            <!-- Allowed Websites -->
                            <div class="space-y-2">
                                <Label>Allowed Websites</Label>
                                <p class="text-xs text-muted-foreground">Enter domains that are allowed to use this widget (without https://)</p>

                                <TagsInput v-model="widgetForm.sites" class="w-full">
                                    <TagsInputItem v-for="(site, index) in widgetForm.sites" :key="index" :value="site">
                                        <TagsInputItemText />
                                        <TagsInputItemDelete />
                                    </TagsInputItem>
                                    <TagsInputInput placeholder="Enter domain (e.g., example.com)" />
                                </TagsInput>

                                <p v-if="widgetForm.errors.sites" class="text-sm text-destructive">
                                    {{ widgetForm.errors.sites }}
                                </p>

                                <p class="text-xs text-muted-foreground">
                                    Leave empty to allow all websites, or specify domains to restrict widget usage
                                </p>
                            </div>

                            <div class="bg-muted rounded-lg p-4">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-sm text-muted-foreground">
                                        Copy and paste this code into your website's <code>&lt;head&gt;</code>.
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="copyEmbedSnippet"
                                            class="gap-2"
                                        >
                                            <Copy class="h-4 w-4" />
                                            Copy
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="openPreview"
                                            class="gap-2"
                                        >
                                            <Eye class="h-4 w-4" />
                                            Preview
                                        </Button>
                                    </div>
                                </div>
                                <div class="bg-background border rounded p-3">
                                    <pre class="text-xs text-foreground overflow-x-auto"><code v-text="embedSnippet"></code></pre>
                                </div>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>


            </div>

            <!-- Right Column - Live Preview -->
            <div class="flex flex-col order-2 lg:order-none">
                <!-- Live Widget Preview -->
                <Card class="flex flex-col lg:h-full min-h-[500px]">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Eye class="h-5 w-5" />
                            Live Preview
                        </CardTitle>
                        <CardDescription>
                            See how your widget will appear on websites. Preview updates when you save changes.
                            <span class="block lg:hidden text-xs mt-1 text-orange-600">
                                Tip: Use "Full Preview" button for better mobile viewing experience.
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col h-full">
                        <div class="space-y-4 flex flex-col flex-1">
                            <!-- Preview Controls -->
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="refreshPreview"
                                        class="gap-2"
                                    >
                                        <RotateCcw class="h-4 w-4" />
                                        <span class="hidden sm:inline">Refresh</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="openFullPreview"
                                        class="gap-2"
                                    >
                                        <Eye class="h-4 w-4" />
                                        <span class="hidden sm:inline">Full Preview</span>
                                    </Button>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Click Save to update preview
                                </div>
                            </div>

                            <!-- Live Preview Iframe -->
                            <div class="border rounded-lg bg-muted/30 p-4 flex-1">
                                <iframe
                                    :key="previewKey"
                                    :src="previewUrl"
                                    class="w-full border-0 rounded bg-white h-[400px] md:h-[500px] lg:h-full lg:min-h-[600px]"
                                    title="Widget Live Preview"
                                    sandbox="allow-scripts allow-same-origin allow-forms"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>


            </div>
        </div>

        <!-- Widget Preview Modal -->
        <PreviewModal
            v-model="showPreviewModal"
            preview-type="widget"
            :preview-url="fullPreviewUrl"
            iframe-title="Widget Preview"
        />

        <!-- Color Picker Dialog -->
        <Dialog v-model:open="showColorPicker">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Palette class="h-5 w-5" />
                        Choose Widget Color
                    </DialogTitle>
                    <DialogDescription>
                        Select a color and shade for your widget's primary theme
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-6">
                    <!-- Color Grid -->
                    <div class="grid grid-cols-8 gap-2">
                        <div
                            v-for="theme in ['black', 'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose', 'slate', 'gray', 'zinc', 'neutral', 'stone']"
                            :key="theme"
                            class="relative cursor-pointer group"
                            @click="appearanceForm.color_theme = theme"
                        >
                            <div
                                class="h-12 w-full rounded-lg border-2 transition-all duration-200 shadow-sm"
                                :class="{
                                    'border-primary ring-2 ring-primary/20': appearanceForm.color_theme === theme,
                                    'border-border hover:border-primary/50 hover:shadow-md': appearanceForm.color_theme !== theme
                                }"
                                :style="{
                                    backgroundColor: `var(--${theme}-${appearanceForm.color_shade})`
                                }"
                            >
                                <div class="flex h-full items-center justify-center">
                                    <div
                                        v-if="appearanceForm.color_theme === theme"
                                        class="h-6 w-6 rounded-full bg-white shadow-lg flex items-center justify-center transform scale-100 transition-transform duration-200"
                                    >
                                        <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"></div>
                                    </div>
                                    <div
                                        v-else
                                        class="h-6 w-6 rounded-full bg-white/80 shadow-sm flex items-center justify-center transform scale-75 opacity-0 group-hover:opacity-100 group-hover:scale-100 transition-all duration-200"
                                    >
                                        <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: `var(--${theme}-${appearanceForm.color_shade})` }"></div>
                                    </div>
                                </div>
                            </div>
                            <p class="mt-1 text-center text-xs font-medium capitalize transition-colors duration-200" :class="{
                                'text-primary': appearanceForm.color_theme === theme,
                                'text-muted-foreground': appearanceForm.color_theme !== theme
                            }">{{ theme }}</p>
                        </div>
                    </div>

                    <!-- Color Shade Slider -->
                    <div class="space-y-2">
                        <Label class="text-sm">Color Intensity</Label>
                        <div class="px-1">
                            <input
                                type="range"
                                min="50"
                                max="950"
                                step="50"
                                v-model="appearanceForm.color_shade"
                                class="w-full h-3 bg-gray-200 rounded-lg appearance-none cursor-pointer slider"
                                :style="{
                                    background: `linear-gradient(to right, var(--${appearanceForm.color_theme}-50), var(--${appearanceForm.color_theme}-950))`
                                }"
                                @input="(e: Event) => {
                                    const target = e.target as HTMLInputElement;
                                    const value = parseInt(target.value);
                                    const validShades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];
                                    const closest = validShades.reduce((prev, curr) =>
                                        Math.abs(curr - value) < Math.abs(prev - value) ? curr : prev
                                    );
                                    appearanceForm.color_shade = closest;
                                }"
                            />
                        </div>
                        <div class="flex justify-between text-xs text-muted-foreground">
                            <span>Light</span>
                            <span class="font-medium">{{ appearanceForm.color_shade }}</span>
                            <span>Dark</span>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="showColorPicker = false">
                        Done
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AgentLayout>
</template>


<script setup lang="ts">
import { computed, ref, watch, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Globe, Save, RotateCcw, Eye, Copy, Palette } from 'lucide-vue-next';
import HeaderActions from '@/components/HeaderActions.vue';
import { toast } from 'vue-sonner';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import PreviewModal from '@/components/PreviewModal.vue';
import {Alert, AlertDescription, AlertTitle} from "@/components/ui/alert";

interface Props {
    agent: any;
    widgetSettings: any;
    appearanceSettings: any;
    embedScriptUrl: string;
}

const props = defineProps<Props>();

// Separate forms for different setting groups
const appearanceForm = useForm({
    welcome_message: props.appearanceSettings.welcome_message,
    chat_window_title: props.appearanceSettings.chat_window_title,
    color_theme: props.appearanceSettings.color_theme,
    color_shade: props.appearanceSettings.color_shade,
});

const widgetForm = useForm({
    position: props.widgetSettings.position,
    show_on_load: props.widgetSettings.show_on_load,
    show_on_load_delay: props.widgetSettings.show_on_load_delay,
    window_size: props.widgetSettings.window_size,
    bubbles: props.widgetSettings.bubbles || [], // Initialize bubbles
    sites: props.widgetSettings.sites || [], // Initialize sites
});

// States
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const newSite = ref('');
const showPreviewModal = ref(false);
const showColorPicker = ref(false);
const previewKey = ref(0);

// Computed properties for header button states
const hasUnsavedChanges = computed(() => {
    // Check if any form data has changed from initial values
    const appearanceChanged = (
        appearanceForm.welcome_message !== (props.appearanceSettings.welcome_message) ||
        appearanceForm.chat_window_title !== (props.appearanceSettings.chat_window_title) ||
        appearanceForm.color_theme !== (props.appearanceSettings.color_theme) ||
        appearanceForm.color_shade !== (props.appearanceSettings.color_shade)
    );

    const widgetChanged = (
        widgetForm.position !== props.widgetSettings.position ||
        widgetForm.show_on_load !== props.widgetSettings.show_on_load ||
        widgetForm.show_on_load_delay !== props.widgetSettings.show_on_load_delay ||
        widgetForm.window_size !== props.widgetSettings.window_size ||
        JSON.stringify(widgetForm.bubbles) !== JSON.stringify(props.widgetSettings.bubbles || []) ||
        JSON.stringify(widgetForm.sites) !== JSON.stringify(props.widgetSettings.sites || [])
    );

    return appearanceChanged || widgetChanged;
});

const hasFormErrors = computed(() => {
    return Object.keys(appearanceForm.errors).length > 0 || Object.keys(widgetForm.errors).length > 0;
});

const updateWidgetSettings = async () => {
    isSubmitting.value = true;
    showSuccessMessage.value = false;

    try {
        // Send appearance settings first
        await new Promise((resolve, reject) => {
            appearanceForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'appearance' }), {
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: () => reject(new Error('Appearance settings failed'))
            });
        });

        // Then send widget settings
        await new Promise((resolve, reject) => {
            widgetForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'widget' }), {
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: () => reject(new Error('Widget settings failed'))
            });
        });

        // Both requests succeeded
        isSubmitting.value = false;
        showSuccessMessage.value = true;

        // Refresh preview after successful save
        previewKey.value += 1;

        setTimeout(() => {
            showSuccessMessage.value = false;
        }, 3000);

    } catch (error) {
        isSubmitting.value = false;
        console.error('Failed to save settings:', error);
    }
};

const restoreDefaults = () => {
    appearanceForm.reset();
    widgetForm.reset();
    showSuccessMessage.value = false;
};



const addSite = () => {
    if (newSite.value && newSite.value.trim() !== '') {
        const domain = newSite.value.trim().toLowerCase();

        // Basic domain validation
        const domainRegex = /^[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;

        if (!domainRegex.test(domain)) {
            toast.error('Please enter a valid domain (e.g., example.com)');
            return;
        }

        // Check if domain already exists
        if (widgetForm.sites.includes(domain)) {
            toast.error('This domain is already in the list');
            return;
        }

        // Add the domain
        widgetForm.sites.push(domain);
        newSite.value = '';
    }
};

const removeSite = (index: number) => {
    widgetForm.sites.splice(index, 1);
};

const openPreview = () => {
    showPreviewModal.value = true;
};



const embedSnippet = computed(() => {
    const scriptLines = [
        '<script',
        `  id="chatbot-embed-script"`,
        `  src="${props.embedScriptUrl}"`,
        `  agent-id="${props.agent.external_id}"`,
        '  async>',
        '</scr' + 'ipt>',
    ];
    return scriptLines.join('\n');
});

const copyEmbedSnippet = async () => {
    try {
        await navigator.clipboard.writeText(embedSnippet.value);
        toast.success('Embed code copied');
    } catch (e) {
        toast.error('Failed to copy');
    }
};

// Live preview URL that updates with current settings
const previewUrl = computed(() => {
    return route('agents.widget.preview', { agent: props.agent.id });
});

// Full preview URL - just adds full-preview=true to the base URL
const fullPreviewUrl = computed(() => {
    const baseUrl = route('agents.widget.preview', { agent: props.agent.id });
    return `${baseUrl}?full-preview=true`;
});

// Methods for preview management
const refreshPreview = () => {
    previewKey.value += 1;
};

const openFullPreview = () => {
    showPreviewModal.value = true;
};

// Preview updates only when Save is clicked - removed auto-update watcher
</script>
