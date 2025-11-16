<template>
    <AgentLayout :agent="agent" title="Widget Configuration">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column - Tabbed Configuration -->
            <div class="order-1 lg:order-none">
                <Card>
                    <CardContent class="p-0">
                        <Tabs v-model="activeTab" class="w-full">
                            <TabsList class="w-full grid grid-cols-6 rounded-none border-b">
                                <TabsTrigger value="text" class="gap-2">
                                    <Type class="h-4 w-4" />
                                    <span class="hidden sm:inline">Text</span>
                                </TabsTrigger>
                                <TabsTrigger value="starters" class="gap-2">
                                    <MessageSquare class="h-4 w-4" />
                                    <span class="hidden sm:inline">Starters</span>
                                </TabsTrigger>
                                <TabsTrigger value="colors" class="gap-2">
                                    <Palette class="h-4 w-4" />
                                    <span class="hidden sm:inline">Colors</span>
                                </TabsTrigger>
                                <TabsTrigger value="behavior" class="gap-2">
                                    <Settings class="h-4 w-4" />
                                    <span class="hidden sm:inline">Behavior</span>
                                </TabsTrigger>
                                <TabsTrigger value="embed" class="gap-2">
                                    <Code class="h-4 w-4" />
                                    <span class="hidden sm:inline">Embed</span>
                                </TabsTrigger>
                                <TabsTrigger value="security" class="gap-2">
                                    <Shield class="h-4 w-4" />
                                    <span class="hidden sm:inline">Security</span>
                                </TabsTrigger>
                            </TabsList>

                            <form @submit.prevent="updateWidgetSettings">
                                <!-- Security Tab -->
                                <TabsContent value="security" class="p-6 space-y-6">
                                    <form @submit.prevent="saveSecuritySettings">
                                        <Alert variant="destructive">
                                            <Shield class="h-4 w-4" />
                                            <AlertTitle>Important Security Notice</AlertTitle>
                                            <AlertDescription>
                                                You must specify allowed websites below, otherwise your widget will not work. This prevents unauthorized usage.
                                            </AlertDescription>
                                        </Alert>

                                        <div class="space-y-2">
                                            <Label>Allowed Websites</Label>
                                            <p class="text-xs text-muted-foreground mb-2">
                                                Enter domains that can use this widget (without https://). Press Enter to save.
                                            </p>

                                            <TagsInput v-model="widgetForm.sites" class="w-full" @update:model-value="saveSecuritySettings">
                                                <TagsInputItem v-for="(site, index) in widgetForm.sites" :key="index" :value="site">
                                                    <TagsInputItemText />
                                                    <TagsInputItemDelete />
                                                </TagsInputItem>
                                                <TagsInputInput placeholder="example.com" @keydown.enter.prevent="saveSecuritySettings" />
                                            </TagsInput>

                                            <p v-if="widgetForm.errors.sites" class="text-sm text-destructive">
                                                {{ widgetForm.errors.sites }}
                                            </p>

                                            <p class="text-xs text-muted-foreground">
                                                Examples: example.com, www.example.com, *.example.com (all subdomains)
                                            </p>
                                        </div>
                                    </form>
                                </TabsContent>

                                <!-- Text Tab -->
                                <TabsContent value="text" class="p-6 space-y-6">
                                    <div class="space-y-2">
                                        <Label for="chat_window_title">Chat Title</Label>
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
                                            First message users see when opening the widget
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>Chat Bubbles</Label>
                                        <p class="text-xs text-muted-foreground mb-2">Quick message prompts to help users start conversations</p>

                                        <TagsInput v-model="widgetForm.bubbles" class="w-full">
                                            <TagsInputItem v-for="(bubble, index) in widgetForm.bubbles" :key="index" :value="bubble">
                                                <TagsInputItemText />
                                                <TagsInputItemDelete />
                                            </TagsInputItem>
                                            <TagsInputInput placeholder="e.g., How can I help you?" />
                                        </TagsInput>

                                        <p v-if="widgetForm.errors.bubbles" class="text-sm text-destructive">
                                            {{ widgetForm.errors.bubbles }}
                                        </p>
                                    </div>

                                    <!-- Save Button -->
                                    <div class="pt-4 border-t">
                                        <Button 
                                            type="submit" 
                                            :disabled="isSubmitting || !hasUnsavedChanges"
                                            class="w-full"
                                        >
                                            <Save class="h-4 w-4 mr-2" />
                                            {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
                                        </Button>
                                        <p v-if="showSuccessMessage" class="text-sm text-green-600 mt-2 text-center">
                                            Settings saved successfully
                                        </p>
                                    </div>
                                </TabsContent>

                                <!-- Starters Tab -->
                                <TabsContent value="starters" class="p-6 space-y-6">
                                    <div class="space-y-4">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h3 class="text-sm font-medium">Conversation Starters</h3>
                                                <p class="text-xs text-muted-foreground mt-1">
                                                    Suggest questions to help users start conversations
                                                </p>
                                            </div>
                                            <Button 
                                                type="button" 
                                                variant="outline" 
                                                size="sm"
                                                @click="showAddStarterDialog = true"
                                            >
                                                <PlusCircle class="mr-2 h-4 w-4" />
                                                Add Starter
                                            </Button>
                                        </div>

                                        <div class="rounded-md border">
                                            <div 
                                                v-if="starters.length === 0"
                                                class="p-4 text-center text-muted-foreground text-sm"
                                            >
                                                No starter messages yet
                                            </div>
                                            <div
                                                v-for="starter in starters"
                                                :key="starter.id"
                                                class="flex items-center justify-between border-b p-3 last:border-0"
                                            >
                                                <div class="flex items-center gap-3">
                                                    <MessageSquare class="h-4 w-4 text-muted-foreground" />
                                                    <div class="text-sm">{{ starter.text }}</div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    class="h-8 w-8 p-0 text-destructive hover:text-destructive/80"
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
                                </TabsContent>

                                <!-- Colors Tab -->
                                <TabsContent value="colors" class="p-6 space-y-6">
                                    <div class="space-y-2">
                                        <Label>Widget Color</Label>
                                        <p class="text-xs text-muted-foreground mb-3">Choose the primary color for your widget</p>
                                        
                                        <div class="relative cursor-pointer inline-block" @click="showColorPicker = true">
                                            <div
                                                class="h-20 w-20 rounded-lg border-2 border-border shadow-sm transition-all duration-200 hover:shadow-md hover:scale-105"
                                                :style="{
                                                    backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})`
                                                }"
                                            >
                                                <div class="flex h-full items-center justify-center">
                                                    <div class="h-10 w-10 rounded-full bg-white/90 shadow-lg flex items-center justify-center">
                                                        <div class="h-6 w-6 rounded-full" :style="{ backgroundColor: `var(--${appearanceForm.color_theme}-${appearanceForm.color_shade})` }"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <p class="text-sm font-medium capitalize">
                                                {{ appearanceForm.color_theme }} - {{ appearanceForm.color_shade }}
                                            </p>
                                        </div>

                                        <p v-if="appearanceForm.errors.color_theme" class="text-sm text-destructive">
                                            {{ appearanceForm.errors.color_theme }}
                                        </p>
                                    </div>
                                </TabsContent>

                                <!-- Behavior Tab -->
                                <TabsContent value="behavior" class="p-6 space-y-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <Label for="position">Position</Label>
                                            <Select v-model="widgetForm.position" @update:model-value="saveBehaviorSettings">
                                                <SelectTrigger class="w-full">
                                                    <SelectValue placeholder="Select position" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="bottom-right">Bottom Right</SelectItem>
                                                    <SelectItem value="bottom-left">Bottom Left</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <p class="text-xs text-muted-foreground">
                                                Where the widget appears on the page
                                            </p>
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="window_size">Window Size</Label>
                                            <Select v-model="widgetForm.window_size" @update:model-value="saveBehaviorSettings">
                                                <SelectTrigger class="w-full">
                                                    <SelectValue placeholder="Select size" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="small">Small</SelectItem>
                                                    <SelectItem value="medium">Medium</SelectItem>
                                                    <SelectItem value="large">Large</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <p class="text-xs text-muted-foreground">
                                                Chat window dimensions
                                            </p>
                                        </div>
                                    </div>

                                    <Separator />

                                    <div class="space-y-4">
                                        <div class="flex items-center justify-between">
                                            <div class="space-y-0.5">
                                                <Label for="show_on_load">Auto-open Widget</Label>
                                                <p class="text-xs text-muted-foreground">
                                                    Automatically show widget when page loads
                                                </p>
                                            </div>
                                            <Switch 
                                                id="show_on_load" 
                                                :model-value="widgetForm.show_on_load" 
                                                @update:model-value="(value: any) => { widgetForm.show_on_load = value; saveBehaviorSettings(); }" 
                                            />
                                        </div>

                                        <div v-if="widgetForm.show_on_load" class="ml-0 space-y-2">
                                            <Label for="show_on_load_delay">Display Delay (ms)</Label>
                                            <Input 
                                                id="show_on_load_delay" 
                                                v-model.number="widgetForm.show_on_load_delay" 
                                                type="number" 
                                                min="0" 
                                                step="100" 
                                                placeholder="500" 
                                                @blur="saveBehaviorSettings"
                                            />
                                            <p class="text-xs text-muted-foreground">
                                                Time to wait before showing the widget
                                            </p>
                                        </div>
                                    </div>

                                    <Alert>
                                        <Info class="h-4 w-4" />
                                        <AlertDescription>
                                            Position, window size, and chat bubbles are visible in preview mode
                                        </AlertDescription>
                                    </Alert>
                                </TabsContent>

                                <!-- Embed Tab -->
                                <TabsContent value="embed" class="p-6 space-y-6">
                                    <div class="space-y-4">
                                        <div>
                                            <h3 class="text-sm font-medium mb-1">Installation Instructions</h3>
                                            <p class="text-xs text-muted-foreground">
                                                Copy and paste this code into your website's <code class="bg-muted px-1 py-0.5 rounded text-xs">&lt;head&gt;</code> section
                                            </p>
                                        </div>
                                        
                                        <div class="bg-muted rounded-lg p-4 space-y-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    @click="copyEmbedSnippet"
                                                    class="gap-2"
                                                >
                                                    <Copy class="h-4 w-4" />
                                                    Copy Code
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    @click="openPreview"
                                                    class="gap-2"
                                                >
                                                    <Eye class="h-4 w-4" />
                                                    Full Preview
                                                </Button>
                                            </div>
                                            <div class="bg-background border rounded p-3">
                                                <pre class="text-xs text-foreground overflow-x-auto"><code v-text="embedSnippet"></code></pre>
                                            </div>
                                        </div>

                                        <Alert>
                                            <Info class="h-4 w-4" />
                                            <AlertDescription>
                                                Make sure to add allowed domains in the Security tab before deploying
                                            </AlertDescription>
                                        </Alert>
                                    </div>
                                </TabsContent>
                            </form>
                        </Tabs>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column - Live Preview -->
            <div class="flex flex-col order-2 lg:order-none">
                <!-- Live Widget Preview -->
                <div class="flex flex-col lg:h-full min-h-[500px] border-2 border-dashed border-border rounded-lg p-6 bg-muted/20">
                    
                    
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
                                    Refresh
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="openFullPreview"
                                    class="gap-2"
                                >
                                    <Eye class="h-4 w-4" />
                                    Full Preview
                                </Button>
                            </div>
                        
                        </div>

                        <!-- Live Preview Iframe -->
                        <div class="border-2 border-dashed border-border rounded-lg bg-background p-4 flex-1 w-full max-w-md mx-auto">
                            <iframe
                                :key="previewKey"
                                :src="previewUrl"
                                class="w-full border-0 rounded bg-white h-[400px] md:h-[500px] lg:h-full lg:min-h-[600px]"
                                title="Widget Live Preview"
                                sandbox="allow-scripts allow-same-origin allow-forms"
                            />
                        </div>
                    </div>
                </div>
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
                    <Button @click="() => { saveColorSettings(); showColorPicker = false; }">
                        Apply & Save
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Starter Dialog -->
        <Dialog v-model:open="showAddStarterDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Starter Message</DialogTitle>
                    <DialogDescription>
                        Create a new conversation starter for your users
                    </DialogDescription>
                </DialogHeader>
                <div class="py-4">
                    <Label class="mb-2" for="starter-message">Message Text</Label>
                    <Textarea
                        id="starter-message"
                        v-model="starterMessageForm.text"
                        placeholder="E.g., How can I track my order?"
                        rows="3"
                    />
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddStarterDialog = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="!starterMessageForm.text.trim()"
                        @click="addStarterMessage"
                    >
                        <PlusCircle class="mr-2 h-4 w-4" />
                        Add Message
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
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Type, Save, RotateCcw, Eye, Copy, Palette, Shield, Info, Settings, Code, MessageSquare, PlusCircle, Trash2 } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import axios from 'axios';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import PreviewModal from '@/components/PreviewModal.vue';
import {Alert, AlertDescription, AlertTitle} from "@/components/ui/alert";

interface Props {
    agent: any;
    widgetSettings: any;
    appearanceSettings: any;
    embedScriptUrl: string;
    starters: Array<{ id: number; text: string }>;
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
const activeTab = ref('security');
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const showPreviewModal = ref(false);
const showColorPicker = ref(false);
const previewKey = ref(0);
const showAddStarterDialog = ref(false);
const starterMessageForm = ref({ text: '' });
const starters = ref(props.starters || []);

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
        // Send text/appearance settings
        await new Promise((resolve, reject) => {
            appearanceForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'appearance' }), {
                preserveScroll: true,
                onSuccess: () => resolve(true),
                onError: () => reject(new Error('Appearance settings failed'))
            });
        });

        // Then send widget settings (bubbles)
        const textForm = useForm({
            bubbles: widgetForm.bubbles,
        });

        await new Promise((resolve, reject) => {
            textForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'widget' }), {
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

const saveSecuritySettings = () => {
    const securityForm = useForm({
        sites: widgetForm.sites,
    });

    securityForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Security settings updated');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update security settings');
        }
    });
};

const saveColorSettings = () => {
    const colorForm = useForm({
        color_theme: appearanceForm.color_theme,
        color_shade: appearanceForm.color_shade,
    });

    colorForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'appearance' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Color updated successfully');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update color');
        }
    });
};

const saveBehaviorSettings = () => {
    const behaviorForm = useForm({
        position: widgetForm.position,
        show_on_load: widgetForm.show_on_load,
        show_on_load_delay: widgetForm.show_on_load_delay,
        window_size: widgetForm.window_size,
    });

    behaviorForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Behavior settings updated');
            previewKey.value += 1;
        },
        onError: () => {
            toast.error('Failed to update behavior settings');
        }
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

// Starters functionality
const addStarterMessage = () => {
    axios.post(route('agents.starters.store', props.agent.id), { text: starterMessageForm.value.text })
        .then((response) => {
            showAddStarterDialog.value = false;
            starters.value.push({
                id: response.data.id,
                text: response.data.text
            });
            starterMessageForm.value.text = '';
            toast.success('Starter message added');
        })
        .catch(() => {
            toast.error('Failed to add starter message');
        });
};

const removeStarter = (id: number) => {
    axios.delete(route('agents.starters.delete', { agent: props.agent.id, id }))
        .then(() => {
            starters.value = starters.value.filter(starter => starter.id !== id);
            toast.success('Starter message removed');
        })
        .catch(() => {
            toast.error('Failed to remove starter message');
        });
};

// Preview updates only when Save is clicked - removed auto-update watcher
</script>
