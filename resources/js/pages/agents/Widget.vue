<template>
    <AgentLayout :agent="agent" title="Widget Configuration" hide-header no-padding full-height>
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
            <ResizablePanelGroup direction="horizontal" auto-save-id="widget-setup-preview" class="min-w-0 flex-1">
                <ResizablePanel :default-size="62" :min-size="35">
                    <div class="flex h-full flex-col overflow-hidden">
                        <Alert v-if="!canEdit" variant="destructive" class="rounded-none border-x-0 border-t-0">
                            <Shield class="h-4 w-4" />
                            <AlertTitle>View Only</AlertTitle>
                            <AlertDescription>
                                You don't have permission to edit widget settings. Contact your team owner to request access.
                            </AlertDescription>
                        </Alert>

                        <div class="flex-1 overflow-y-auto" :class="{ 'pointer-events-none opacity-60 select-none': !canEdit }">
                            <div v-if="attached_template" class="px-6 pt-6">
                                <AgentTemplateBanner :agent-id="agent.external_id" :attached-template="attached_template" />
                            </div>

                            <!-- Text Panel -->
                            <div v-if="activeTab === 'info'" class="space-y-6 p-6">
                                <form @submit.prevent="updateWidgetSettings" class="space-y-6">
                                    <TextSection
                                        v-model:welcome-message="appearanceForm.welcome_message"
                                        v-model:chat-window-title="appearanceForm.chat_window_title"
                                        v-model:chat-footer-text="appearanceForm.chat_footer_text"
                                        v-model:composer-placeholder="appearanceForm.composer_placeholder"
                                        :errors="appearanceForm.errors as Record<string, string | undefined>"
                                        :disabled="locked"
                                    />

                                    <div v-if="!locked" class="border-t pt-4">
                                        <Button type="submit" :disabled="!canEdit || isSubmitting || !hasUnsavedChanges" size="sm">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                        </Button>
                                        <p v-if="showSuccessMessage" class="mt-2 text-sm text-green-600">Settings saved successfully</p>
                                    </div>
                                </form>
                            </div>

                            <!-- Starters Panel -->
                            <div v-if="activeTab === 'starters'" class="space-y-6 p-6">
                                <div class="space-y-4">
                                    <StartersSection v-model:starters="starters" :disabled="locked" />

                                    <div v-if="!locked">
                                        <Button type="button" size="sm" :disabled="!canEdit || isSavingStarters" @click="saveStarters">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSavingStarters ? 'Saving...' : 'Save starters' }}
                                        </Button>
                                    </div>
                                </div>

                                <Separator />

                                <div class="space-y-4">
                                    <div>
                                        <h3 class="text-sm font-medium">Chat Bubbles</h3>
                                        <p class="text-muted-foreground mt-1 text-xs">
                                            Quick message prompts shown next to the widget bubble. Press Enter to add each one.
                                        </p>
                                    </div>

                                    <TagsInput v-model="widgetForm.bubbles" :disabled="locked" class="w-full">
                                        <TagsInputItem v-for="(bubble, index) in widgetForm.bubbles" :key="index" :value="bubble">
                                            <TagsInputItemText />
                                            <TagsInputItemDelete />
                                        </TagsInputItem>
                                        <TagsInputInput placeholder="e.g., How can I help you?" />
                                    </TagsInput>

                                    <p v-if="widgetForm.errors.bubbles" class="text-destructive text-sm">
                                        {{ widgetForm.errors.bubbles }}
                                    </p>

                                    <div v-if="!locked">
                                        <Button type="button" size="sm" :disabled="!canEdit || isSavingBubbles" @click="saveBubbles">
                                            <Save class="mr-2 h-4 w-4" />
                                            {{ isSavingBubbles ? 'Saving...' : 'Save bubbles' }}
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Models Panel -->
                            <div v-if="activeTab === 'models'" class="space-y-6 p-6">
                                <ModelsSection
                                    v-model:models="modelsForm.models"
                                    v-model:llm-settings="modelsForm.llm_settings"
                                    :catalog="catalog"
                                    single
                                    show-response-settings
                                    :disabled="locked"
                                />

                                <div v-if="!locked" class="border-t pt-4">
                                    <Button type="button" size="sm" :disabled="!canEdit || !hasModelChanges || isSavingModels" @click="saveModels">
                                        <Save class="mr-2 h-4 w-4" />
                                        {{ isSavingModels ? 'Saving...' : 'Save model' }}
                                    </Button>
                                </div>
                            </div>

                            <!-- Branding Panel -->
                            <div v-if="activeTab === 'branding'" class="space-y-6 p-6">
                                <BrandingSection
                                    v-model:color-theme="appearanceForm.color_theme"
                                    v-model:color-shade="appearanceForm.color_shade"
                                    :avatar-preview="avatarPreview"
                                    :logo-preview="logoPreview"
                                    :errors="appearanceForm.errors as Record<string, string | undefined>"
                                    :disabled="locked"
                                    @color-apply="saveColorSettings"
                                    @avatar-select="onAvatarSelect"
                                    @avatar-remove="removeAvatar"
                                    @logo-select="onLogoSelect"
                                    @logo-remove="removeLogo"
                                />
                            </div>

                            <!-- Behavior Panel -->
                            <div v-if="activeTab === 'behavior'" class="space-y-6 p-6">
                                <BehaviorSection
                                    v-model:position="widgetForm.position"
                                    v-model:window-size="widgetForm.window_size"
                                    v-model:show-on-load="widgetForm.show_on_load"
                                    v-model:show-on-load-delay="widgetForm.show_on_load_delay"
                                    v-model:theme-color="widgetForm.theme_color"
                                    v-model:theme-shade="widgetForm.theme_shade"
                                    show-widget-color
                                    :disabled="locked"
                                    @update:position="saveBehaviorSettings"
                                    @update:window-size="saveBehaviorSettings"
                                    @update:show-on-load="saveBehaviorSettings"
                                    @theme-apply="saveBehaviorSettings"
                                />

                                <div v-if="widgetForm.show_on_load" class="border-t pt-4">
                                    <Button type="button" size="sm" @click="saveBehaviorSettings" :disabled="locked || !canEdit">
                                        <Save class="mr-2 h-4 w-4" />
                                        Save delay
                                    </Button>
                                </div>

                                <Alert>
                                    <Info class="h-4 w-4" />
                                    <AlertDescription>Position, window size, theme color, and chat bubbles are visible in preview mode</AlertDescription>
                                </Alert>
                            </div>

                            <!-- Embed Panel -->
                            <div v-if="activeTab === 'embed'" class="space-y-6 p-6">
                                <form @submit.prevent="saveSecuritySettings">
                                    <Alert v-if="!widgetForm.sites || widgetForm.sites.length === 0" variant="destructive" class="mb-4">
                                        <Shield class="h-4 w-4" />
                                        <AlertTitle>Important</AlertTitle>
                                        <AlertDescription>
                                            You must specify allowed websites below, otherwise your widget will not work.
                                        </AlertDescription>
                                    </Alert>

                                    <SecuritySection
                                        v-model:sites="widgetForm.sites"
                                        :show-is-public="false"
                                        show-sites
                                        :errors="widgetForm.errors as Record<string, string | undefined>"
                                        :disabled="locked"
                                    />

                                    <div v-if="!locked" class="pt-3">
                                        <Button type="submit" :disabled="!canEdit || !hasSecurityChanges" size="sm">
                                            <Save class="mr-2 h-4 w-4" />
                                            Save Domains
                                        </Button>
                                    </div>
                                </form>

                                <!-- Language Selection -->
                                <div class="space-y-2 border-t pt-4">
                                    <Label for="widget-language">Widget Language</Label>
                                    <Select v-model="selectedLanguage" :disabled="locked" @update:model-value="saveBehaviorSettings">
                                        <SelectTrigger id="widget-language" class="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="(label, code) in availableLanguages" :key="code" :value="code">
                                                {{ label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-muted-foreground text-xs">
                                        Select the language for the widget interface. Auto-detect will use the visitor's browser language.
                                    </p>
                                </div>

                                <!-- Theme Selection -->
                                <div class="space-y-2 border-t pt-4">
                                    <Label for="widget-theme">Widget Theme</Label>
                                    <Select v-model="selectedTheme" :disabled="locked" @update:model-value="saveBehaviorSettings">
                                        <SelectTrigger id="widget-theme" class="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="auto">Auto (System)</SelectItem>
                                            <SelectItem value="light">Light</SelectItem>
                                            <SelectItem value="dark">Dark</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-muted-foreground text-xs">
                                        Choose the widget color scheme. Auto follows the visitor's operating system preference.
                                    </p>
                                </div>

                                <!-- Display Mode -->
                                <div class="flex items-center justify-between border-t pt-4">
                                    <div class="space-y-0.5">
                                        <Label for="widget-bar-mode">Bar mode</Label>
                                        <p class="text-muted-foreground text-xs">
                                            Embed as a launcher bar that opens a slider panel, instead of a floating chat bubble.
                                        </p>
                                    </div>
                                    <Switch id="widget-bar-mode" v-model="isBarMode" />
                                </div>

                                <!-- Embed Code -->
                                <div class="space-y-4 border-t pt-4">
                                    <div>
                                        <h3 class="mb-1 text-sm font-medium">Installation Code</h3>
                                        <p class="text-muted-foreground text-xs">
                                            Copy and paste this code into your website's
                                            <code class="bg-muted rounded px-1 py-0.5 text-xs">&lt;head&gt;</code> section
                                        </p>
                                    </div>

                                    <div class="bg-muted space-y-3 rounded-lg p-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button type="button" variant="outline" size="sm" @click="copyEmbedSnippet" class="gap-2">
                                                <Copy class="h-4 w-4" />
                                                Copy Code
                                            </Button>
                                            <Button type="button" variant="outline" size="sm" @click="openPreview" class="gap-2">
                                                <Eye class="h-4 w-4" />
                                                Full Preview
                                            </Button>
                                        </div>
                                        <div class="bg-background rounded border p-3">
                                            <pre class="text-foreground overflow-x-auto text-xs"><code v-text="embedSnippet"></code></pre>
                                        </div>
                                    </div>

                                    <Alert v-if="isProtected">
                                        <Shield class="h-4 w-4" />
                                        <AlertTitle>This widget is protected</AlertTitle>
                                        <AlertDescription>
                                            Replace
                                            <code class="bg-muted rounded px-1 py-0.5 text-xs">YOUR_SIGNED_TOKEN</code>
                                            with a JWT you sign on your server for each visitor — without a valid token the widget won't load.
                                            <Link :href="route('agents.identity', { agent: props.agent.external_id })" class="text-primary font-medium hover:underline">
                                                Open Security settings
                                            </Link>
                                        </AlertDescription>
                                    </Alert>

                                    <Button type="button" variant="outline" class="w-full justify-start gap-2" @click="showApiDocsDialog = true">
                                        <Terminal class="h-4 w-4" />
                                        JavaScript API Documentation
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </ResizablePanel>

                <ResizableHandle with-handle @dragging="isPreviewResizing = $event" />

                <!-- Live Preview -->
                <ResizablePanel :default-size="38" :min-size="25">
                    <div class="bg-background flex h-full flex-col">
                        <div class="flex flex-shrink-0 items-center justify-between border-b px-4 py-3">
                            <span class="text-muted-foreground text-sm font-medium">Live Preview</span>
                            <div class="flex items-center gap-1">
                                <RadioGroup v-model="mode" orientation="horizontal" class="mr-2 flex items-center gap-3">
                                    <div class="flex items-center gap-1.5">
                                        <RadioGroupItem id="preview-mode-widget" value="widget" />
                                        <Label for="preview-mode-widget" class="text-muted-foreground cursor-pointer text-xs">Widget</Label>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <RadioGroupItem id="preview-mode-bar" value="bar" />
                                        <Label for="preview-mode-bar" class="text-muted-foreground cursor-pointer text-xs">Bar</Label>
                                    </div>
                                </RadioGroup>
                                <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5" @click="refreshPreview">
                                    <RotateCcw class="h-3.5 w-3.5" />
                                    <span class="text-xs">Refresh</span>
                                </Button>
                                <Button type="button" variant="ghost" size="sm" class="h-8 gap-1.5" @click="openFullPreview">
                                    <Eye class="h-3.5 w-3.5" />
                                    <span class="text-xs">Preview</span>
                                </Button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-hidden">
                            <iframe
                                :key="previewKey"
                                :src="previewUrl"
                                class="h-full w-full border-0"
                                :class="{ 'pointer-events-none': isPreviewResizing }"
                                title="Widget Live Preview"
                            />
                        </div>
                    </div>
                </ResizablePanel>
            </ResizablePanelGroup>
        </div>

        <PreviewModal v-model="showPreviewModal" preview-type="widget" :preview-url="fullPreviewUrl" iframe-title="Widget Preview" />

        <!-- JavaScript API Documentation Dialog -->
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
                            <div class="overflow-hidden rounded-lg bg-zinc-950">
                                <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-900 px-4 py-2">
                                    <span class="text-xs font-medium text-zinc-400">JavaScript</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200"
                                        @click="copyApiCode(eventsCodeExample)"
                                    >
                                        <Copy class="mr-1.5 h-3.5 w-3.5" />
                                        <span class="text-xs">Copy</span>
                                    </Button>
                                </div>
                                <pre
                                    class="overflow-x-auto p-4 text-sm"
                                ><code class="text-zinc-300 font-mono leading-relaxed"><span class="text-zinc-500">// When chat window opens</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'open'</span>, () =&gt; {
  <span class="text-sky-400">console</span>.<span class="text-emerald-400">log</span>(<span class="text-orange-300">'Chat opened'</span>);
});

<span class="text-zinc-500">// When chat window closes</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'close'</span>, () =&gt; {
  <span class="text-sky-400">console</span>.<span class="text-emerald-400">log</span>(<span class="text-orange-300">'Chat closed'</span>);
});

<span class="text-zinc-500">// When widget is ready</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'ready'</span>, () =&gt; {
  <span class="text-sky-400">console</span>.<span class="text-emerald-400">log</span>(<span class="text-orange-300">'Widget initialized'</span>);
});

<span class="text-zinc-500">// Unsubscribe from events</span>
<span class="text-violet-400">const</span> <span class="text-zinc-200">unsubscribe</span> = <span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">on</span>(<span class="text-orange-300">'open'</span>, <span class="text-zinc-200">myCallback</span>);
<span class="text-zinc-200">unsubscribe</span>(); <span class="text-zinc-500">// Stop listening</span>

<span class="text-zinc-500">// Or use window.chatvia.off()</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">off</span>(<span class="text-orange-300">'open'</span>, <span class="text-zinc-200">myCallback</span>); <span class="text-zinc-500">// Remove specific callback</span>
<span class="text-sky-400">window</span>.<span class="text-amber-300">chatvia</span>.<span class="text-emerald-400">off</span>(<span class="text-orange-300">'open'</span>); <span class="text-zinc-500">// Remove all callbacks for this event</span></code></pre>
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

<script setup lang="ts">
import AgentTemplateBanner from '@/components/agents/AgentTemplateBanner.vue';
import BehaviorSection from '@/components/agents/sections/BehaviorSection.vue';
import BrandingSection from '@/components/agents/sections/BrandingSection.vue';
import ModelsSection from '@/components/agents/sections/ModelsSection.vue';
import SecuritySection from '@/components/agents/sections/SecuritySection.vue';
import StartersSection from '@/components/agents/sections/StartersSection.vue';
import TextSection from '@/components/agents/sections/TextSection.vue';
import PreviewModal from '@/components/PreviewModal.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { ResizableHandle, ResizablePanel, ResizablePanelGroup } from '@/components/ui/resizable';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { Brain, Code, Copy, Eye, Image, Info, MessageSquare, RotateCcw, Save, Settings, Shield, Terminal, Type, Zap } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { route } from 'ziggy-js';

type AIModel = Domain.Chat.Data.LlmModelData;

interface LlmSettings {
    temperature: number;
    top_k: number;
    top_p: number;
    presence_penalty: number;
    frequency_penalty: number;
}

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
    widgetSettings: any;
    appearanceSettings: any;
    embedScriptUrl: string;
    availableLanguages: Record<string, string>;
    starters: string[];
    has_permission: boolean;
    agent_template_id: string | null;
    attached_template: { id: string; name: string } | null;
    catalog: AIModel[];
    selectedModels: string[];
    llmSettings: LlmSettings;
    defaultLlmSettings: LlmSettings;
}

const props = defineProps<Props>();

const canEdit = computed(() => props.has_permission);

// When a template is attached the agent reads its config from the template, so
// the per-agent setup fields are locked until the user detaches.
const locked = computed(() => props.agent_template_id !== null);

const appearanceForm = useForm({
    welcome_message: props.appearanceSettings.welcome_message ?? '',
    chat_window_title: props.appearanceSettings.chat_window_title ?? '',
    chat_footer_text: props.appearanceSettings.chat_footer_text ?? '',
    composer_placeholder: props.appearanceSettings.composer_placeholder ?? '',
    color_theme: props.appearanceSettings.color_theme,
    color_shade: props.appearanceSettings.color_shade,
});

const widgetForm = useForm({
    position: props.widgetSettings.position,
    show_on_load: props.widgetSettings.show_on_load,
    show_on_load_delay: props.widgetSettings.show_on_load_delay,
    window_size: props.widgetSettings.window_size,
    theme_color: props.widgetSettings.theme_color ?? props.appearanceSettings.color_theme,
    theme_shade: props.widgetSettings.theme_shade ?? props.appearanceSettings.color_shade,
    bubbles: props.widgetSettings.bubbles || [],
    sites: props.widgetSettings.sites || [],
});

const modelsForm = useForm({
    models: [...props.selectedModels],
    llm_settings: { ...props.llmSettings } as LlmSettings,
});

const originalModelIds = ref<string[]>([...props.selectedModels]);
const originalLlmSettings = ref<LlmSettings>({ ...props.llmSettings });

// Persona lives at the agent level (top-level sidebar item) since it's shared
// across every surface — it's intentionally NOT a sub-tab here.
const menuItems = [
    { value: 'info', label: 'Text', icon: Type },
    { value: 'starters', label: 'Starters', icon: MessageSquare },
    { value: 'models', label: 'Model', icon: Brain },
    { value: 'branding', label: 'Branding', icon: Image },
    { value: 'behavior', label: 'Behavior', icon: Settings },
    { value: 'embed', label: 'Embed', icon: Code },
];

const activeTab = ref('info');
const isPreviewResizing = ref(false);
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const showPreviewModal = ref(false);
const previewKey = ref(0);
const starters = ref<string[]>(props.starters || []);
const isSavingStarters = ref(false);
const isSavingBubbles = ref(false);
const isSavingModels = ref(false);
const avatarPreview = ref<string | null>(props.appearanceSettings.avatar || null);
const logoPreview = ref<string | null>(props.appearanceSettings.logo || null);
const showApiDocsDialog = ref(false);
const selectedLanguage = ref(props.widgetSettings.language ?? 'auto');
const selectedTheme = ref<'auto' | 'light' | 'dark'>(props.widgetSettings.theme ?? 'auto');

// A protected widget (is_public = false) requires a signed `data-auth` JWT to
// load; a public one embeds with no token. The toggle lives on the Security
// page now — here we just read the current state to shape the embed snippet.
const isProtected = computed(() => !props.agent.is_public);

// Widget display mode: 'widget' (floating chat bubble) or 'bar' (launcher bar).
// Local-only choice that drives the live preview and the generated embed snippet.
const mode = ref<'widget' | 'bar'>('widget');
const isBarMode = computed({
    get: () => mode.value === 'bar',
    set: (value: boolean) => {
        mode.value = value ? 'bar' : 'widget';
    },
});

watch(mode, () => {
    previewKey.value += 1;
});

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
    } catch (error) {
        console.error('Failed to copy code:', error);
        toast.error('Failed to copy code. Please try again.');
    }
};

const hasUnsavedChanges = computed(() => {
    return (
        appearanceForm.welcome_message !== (props.appearanceSettings.welcome_message ?? '') ||
        appearanceForm.chat_window_title !== (props.appearanceSettings.chat_window_title ?? '') ||
        appearanceForm.chat_footer_text !== (props.appearanceSettings.chat_footer_text ?? '') ||
        appearanceForm.composer_placeholder !== (props.appearanceSettings.composer_placeholder ?? '')
    );
});

const hasSecurityChanges = computed(() => {
    return JSON.stringify(widgetForm.sites) !== JSON.stringify(props.widgetSettings.sites || []);
});

const hasModelChanges = computed(() => {
    const modelsChanged =
        modelsForm.models.length !== originalModelIds.value.length ||
        modelsForm.models.some((id) => !originalModelIds.value.includes(id));
    const settingsChanged = (Object.keys(modelsForm.llm_settings) as (keyof LlmSettings)[]).some(
        (key) => modelsForm.llm_settings[key] !== originalLlmSettings.value[key],
    );
    return modelsChanged || settingsChanged;
});

const updateWidgetSettings = () => {
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
            previewKey.value += 1;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const saveSecuritySettings = () => {
    const securityForm = useForm({ sites: widgetForm.sites });

    securityForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Security settings updated');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update security settings');
        },
    });
};

const saveColorSettings = () => {
    const colorForm = useForm({
        color_theme: appearanceForm.color_theme,
        color_shade: appearanceForm.color_shade,
    });

    colorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Color updated successfully');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update color');
        },
    });
};

const saveBehaviorSettings = () => {
    const behaviorForm = useForm({
        position: widgetForm.position,
        show_on_load: widgetForm.show_on_load,
        show_on_load_delay: widgetForm.show_on_load_delay,
        window_size: widgetForm.window_size,
        theme_color: widgetForm.theme_color,
        theme_shade: widgetForm.theme_shade,
        language: selectedLanguage.value,
        theme: selectedTheme.value,
    });

    behaviorForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Behavior settings updated');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update behavior settings');
        },
    });
};

const openPreview = () => {
    showPreviewModal.value = true;
};

const embedSnippet = computed(() => {
    const scriptLines = [
        '<script',
        `  id="chatbot-embed-script"`,
        `  src="${props.embedScriptUrl}"`,
        `  data-agent-id="${props.agent.external_id}"`,
        `  data-language="${selectedLanguage.value}"`,
        `  data-theme="${selectedTheme.value}"`,
    ];
    if (mode.value === 'bar') {
        scriptLines.push(`  data-mode="bar"`);
    }
    if (isProtected.value) {
        scriptLines.push(`  data-auth="YOUR_SIGNED_TOKEN"`);
    }
    scriptLines.push('  defer>');
    scriptLines.push('</scr' + 'ipt>');
    return scriptLines.join('\n');
});

const copyEmbedSnippet = async () => {
    try {
        await navigator.clipboard.writeText(embedSnippet.value);
        toast.success('Embed code copied');
    } catch (_e) {
        toast.error('Failed to copy');
    }
};

const previewUrl = computed(() => {
    const baseUrl = route('agents.widget.preview', { agent: props.agent.external_id });
    return `${baseUrl}?language=${selectedLanguage.value}&mode=${mode.value}&theme=${selectedTheme.value}`;
});

const fullPreviewUrl = computed(() => {
    const baseUrl = route('agents.widget.preview', { agent: props.agent.external_id });
    return `${baseUrl}?full-preview=true&language=${selectedLanguage.value}&mode=${mode.value}&theme=${selectedTheme.value}`;
});

const refreshPreview = () => {
    previewKey.value += 1;
};

const openFullPreview = () => {
    showPreviewModal.value = true;
};

const saveStarters = () => {
    isSavingStarters.value = true;
    axios
        .put(route('agents.starters.update', { agent: props.agent.external_id }), { text: starters.value })
        .then(() => {
            toast.success('Starters saved');
            previewKey.value += 1;
        })
        .catch(() => {
            toast.error('Failed to save starters');
        })
        .finally(() => {
            isSavingStarters.value = false;
        });
};

const saveBubbles = () => {
    isSavingBubbles.value = true;
    const bubblesForm = useForm({ bubbles: widgetForm.bubbles });
    bubblesForm.post(route('agents.settings.update', { agent: props.agent.external_id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Bubbles saved');
            previewKey.value += 1;
            isSavingBubbles.value = false;
        },
        onError: () => {
            toast.error('Failed to save bubbles');
            isSavingBubbles.value = false;
        },
    });
};

// Diff selection against the originally-enabled list, then fire one toggle
// PATCH per change. After model writes settle, persist the LLM tuning sliders.
const saveModels = async () => {
    isSavingModels.value = true;

    try {
        const toEnable = modelsForm.models.filter((id) => !originalModelIds.value.includes(id));
        const toDisable = originalModelIds.value.filter((id) => !modelsForm.models.includes(id));

        for (const modelId of toEnable) {
            await axios.patch(route('agents.models.toggle', { agent: props.agent.external_id }), {
                model_id: modelId,
                status: true,
            });
        }
        for (const modelId of toDisable) {
            await axios.patch(route('agents.models.toggle', { agent: props.agent.external_id }), {
                model_id: modelId,
                status: false,
            });
        }

        await axios.patch(route('agents.models.settings.update', { agent: props.agent.external_id }), modelsForm.llm_settings);

        originalModelIds.value = [...modelsForm.models];
        originalLlmSettings.value = { ...modelsForm.llm_settings };
        toast.success('Model saved');
        previewKey.value += 1;
    } catch (error) {
        console.error('Failed to save model:', error);
        toast.error('Failed to save model');
    } finally {
        isSavingModels.value = false;
    }
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
            previewKey.value += 1;
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
                previewKey.value += 1;
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
            previewKey.value += 1;
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
                previewKey.value += 1;
            },
            onError: () => {
                toast.error('Failed to remove logo');
            },
        },
    );
};
</script>
