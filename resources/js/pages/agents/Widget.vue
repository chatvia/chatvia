<template>
    <AgentLayout :agent="agent" title="Widget Configuration">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Globe class="h-5 w-5" />
                        Widget Configuration
                    </CardTitle>
                    <CardDescription>
                        Configure your agent as an embeddable chat widget for your website.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form id="widget-settings-form" @submit.prevent="updateWidgetSettings" class="space-y-8">
                        <!-- Widget Enable/Disable Toggle -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 border border-input rounded-lg bg-muted/50">
                                <div class="space-y-1">
                                    <h3 class="text-lg font-semibold text-foreground">Widget Status</h3>
                                    <p class="text-sm text-muted-foreground">
                                        {{ widgetForm.enabled ? 'Widget is currently active and can be embedded on websites.' : 'Widget is disabled and cannot be embedded on websites.' }}
                                    </p>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="widget_enabled"
                                        v-model="widgetForm.enabled"
                                        type="checkbox"
                                        class="sr-only"
                                    />
                                    <button
                                        type="button"
                                        @click="toggleWidget"
                                        :class="[
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                            widgetForm.enabled ? 'bg-primary' : 'bg-input'
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'inline-block h-4 w-4 transform rounded-full bg-white transition-transform',
                                                widgetForm.enabled ? 'translate-x-6' : 'translate-x-1'
                                            ]"
                                        />
                                    </button>
                                    <label for="widget_enabled" class="text-sm font-medium text-foreground">
                                        {{ widgetForm.enabled ? 'Enabled' : 'Disabled' }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Form Content - Disabled when widget is disabled -->
                        <div :class="{ 'opacity-50 pointer-events-none': !widgetForm.enabled }">
                            <!-- Appearance Settings -->
                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <Label for="theme">Theme</Label>
                                        <Select v-model="widgetForm.theme" :disabled="!widgetForm.enabled">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Select theme" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="auto">Auto (Follows website)</SelectItem>
                                                <SelectItem value="light">Light</SelectItem>
                                                <SelectItem value="dark">Dark</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="position">Position</Label>
                                        <Select v-model="widgetForm.position" :disabled="!widgetForm.enabled">
                                            <SelectTrigger class="w-full">
                                                <SelectValue placeholder="Select position" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="bottom-right">Bottom Right</SelectItem>
                                                <SelectItem value="bottom-left">Bottom Left</SelectItem>
                                                <SelectItem value="top-right">Top Right</SelectItem>
                                                <SelectItem value="top-left">Top Left</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="window_size">Window Size</Label>
                                        <Select v-model="widgetForm.window_size" :disabled="!widgetForm.enabled">
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

                                    <div class="space-y-2">
                                        <Label for="primary_color">Primary Color</Label>
                                        <div class="flex items-center gap-3">
                                            <Input id="primary_color" v-model="widgetForm.primary_color" type="color" :disabled="!widgetForm.enabled" class="w-12 h-10 p-1" />
                                            <Input v-model="widgetForm.primary_color" type="text" :disabled="!widgetForm.enabled" placeholder="#0000FF" />
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <Switch id="show_theme_switcher" :model-value="widgetForm.show_theme_switcher" @update:model-value="(value: any) => widgetForm.show_theme_switcher = value" :disabled="!widgetForm.enabled" />
                                    <Label for="show_theme_switcher">Show theme switcher in widget</Label>
                                </div>
                            </div>

                            <!-- Behavior Settings -->
                            <div class="space-y-4 mt-3">

                                <div class="space-y-4">
                                    <div class="flex items-center gap-2">
                                        <Switch id="show_on_load" :model-value="widgetForm.show_on_load" @update:model-value="(value: any) => widgetForm.show_on_load = value" :disabled="!widgetForm.enabled" />
                                        <Label for="show_on_load">Show widget automatically when page loads</Label>
                                    </div>

                                    <div v-if="widgetForm.show_on_load" class="ml-6 space-y-1">
                                        <Label for="show_on_load_delay">Delay (milliseconds)</Label>
                                        <Input id="show_on_load_delay" v-model.number="widgetForm.show_on_load_delay" type="number" min="0" step="100" :disabled="!widgetForm.enabled" placeholder="500" />
                                    </div>
                                </div>
                            </div>

                            <!-- Embed Code Preview -->
                            <div class="space-y-6">


                                <!-- Allowed Websites -->
                                <div class="space-y-2 mt-4">
                                    <Label>Allowed Websites</Label>
                                    <p class="text-xs text-muted-foreground">Enter domains that are allowed to use this widget (without https://)</p>

                                    <TagsInput v-model="widgetForm.sites" class="w-full" :disabled="!widgetForm.enabled">
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
                                                :disabled="!widgetForm.enabled"
                                                class="gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
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
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- Actions (outside Card) -->
            <div class="flex items-center justify-between pt-6">
                <Button
                    type="button"
                    variant="outline"
                    @click="restoreDefaults"
                    :disabled="!widgetForm.enabled"
                    class="gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <RotateCcw class="h-4 w-4" />
                    Restore Default Settings
                </Button>

                <div class="flex items-center gap-3">
                    <FormSuccessMessage
                        :show="showSuccessMessage"
                        message="Widget settings saved successfully"
                    />

                    <!-- General form errors -->
                    <div v-if="Object.keys(widgetForm.errors).length > 0 && !showSuccessMessage" class="text-sm text-destructive">
                        Please fix the errors above and try again.
                    </div>

                    <Button
                        :disabled="isSubmitting || !widgetForm.enabled"
                        size="lg"
                        type="submit"
                        form="widget-settings-form"
                        class="gap-2 min-w-[160px] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Save v-if="!isSubmitting" class="h-4 w-4" />
                        <div v-else class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                        {{ isSubmitting ? 'Saving...' : 'Save Widget' }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Widget Preview Modal -->
        <PreviewModal
            v-model="showPreviewModal"
            preview-type="widget"
            :preview-url="route('agents.widget.preview', { agent: agent.id })"
            iframe-title="Widget Preview"
        />
    </AgentLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import { Globe, LoaderCircle, Save, RotateCcw, Eye, X, Copy } from 'lucide-vue-next';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';
import { toast } from 'vue-sonner';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import PreviewModal from '@/components/PreviewModal.vue';

interface Props {
    agent: any;
    widgetSettings: any;
    embedScriptUrl: string;
}

const props = defineProps<Props>();

// Form for widget settings
const widgetForm = useForm({
    primary_color: props.widgetSettings.primary_color,
    theme: props.widgetSettings.theme,
    show_theme_switcher: props.widgetSettings.show_theme_switcher,
    position: props.widgetSettings.position,
    show_on_load: props.widgetSettings.show_on_load,
    show_on_load_delay: props.widgetSettings.show_on_load_delay,
    window_size: props.widgetSettings.window_size,
    sites: props.widgetSettings.sites || [], // Initialize sites
    enabled: props.widgetSettings.enabled ?? true, // Initialize enabled field with default true
});

// States
const isSubmitting = ref(false);
const showSuccessMessage = ref(false);
const newSite = ref('');
const showPreviewModal = ref(false);


const updateWidgetSettings = () => {
    isSubmitting.value = true;
    showSuccessMessage.value = false;

    widgetForm.post(route('agents.settings.update', { agent: props.agent.id, group: 'widget' }), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            showSuccessMessage.value = true;
            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const restoreDefaults = () => {
    widgetForm.reset();
    showSuccessMessage.value = false;
};

const toggleWidget = () => {
    widgetForm.enabled = !widgetForm.enabled;
    updateWidgetSettings();
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
        '<script>',
        "  const script = document.createElement('script');",
        "  script.id = 'chatbot-embed-script';",
        `  script.src = '${props.embedScriptUrl}';`,
        '  script.async = true;',
        '',
        `  script.setAttribute('agent-id', '${props.agent.external_id}');`,
        '  document.body.appendChild(script);',
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
</script>
