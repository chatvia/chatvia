<template>
    <AgentLayout :agent="agent" title="Embed Chat">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Eye class="h-5 w-5" />
                        Embed Chat
                    </CardTitle>
                    <CardDescription>
                        Embed your chat agent on your website for a seamless chat experience.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-8">


                        <!-- Embed Code -->
                        <div class="space-y-4">

                            <div class="space-y-4">
                                <!-- Size Options -->
                                <div class="space-y-3">
                                    <Label class="text-sm font-medium">Embed Configuration</Label>
                                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                                        <div class="space-y-2">
                                            <Label for="embed_width" class="text-xs">Width</Label>
                                            <Input
                                                id="embed_width"
                                                v-model="iframeOptions.width"
                                                type="text"
                                                placeholder="100%"
                                                class="text-sm"

                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="embed_height" class="text-xs">Height</Label>
                                            <Input
                                                id="embed_height"
                                                v-model="iframeOptions.height"
                                                type="text"
                                                placeholder="600px"
                                                class="text-sm"
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="embed_border" class="text-xs">Border</Label>
                                            <Select v-model="iframeOptions.border">
                                                <SelectTrigger class="w-full">
                                                    <SelectValue placeholder="Border style" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">None</SelectItem>
                                                    <SelectItem value="1px solid #e5e7eb">Light</SelectItem>
                                                    <SelectItem value="2px solid #d1d5db">Medium</SelectItem>
                                                    <SelectItem value="3px solid #9ca3af">Heavy</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="space-y-2">
                                            <Label for="embed_title" class="text-xs">Title (for accessibility)</Label>
                                            <Input
                                                id="embed_title"
                                                v-model="iframeOptions.title"
                                                type="text"
                                                placeholder="Chat with our AI assistant"
                                                class="text-sm"
                                            />
                                        </div>
                                    </div>

                                    <!-- Quick Size Presets -->
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="setPresetSize('full')"
                                            class="text-xs"
                                        >
                                            Full Width
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="setPresetSize('large')"
                                            class="text-xs"
                                        >
                                            Large (800x600)
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="setPresetSize('medium')"
                                            class="text-xs"
                                        >
                                            Medium (600x500)
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="setPresetSize('small')"
                                            class="text-xs"
                                        >
                                            Small (400x400)
                                        </Button>
                                    </div>
                                </div>

                                <!-- Generated Embed Code -->
                                <div class="bg-muted rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-sm text-muted-foreground">
                                            HTML Embed Code
                                        </p>
                                        <div class="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="showPreviewModal = true"
                                                class="gap-2"
                                            >
                                                <Eye class="h-4 w-4" />
                                                Preview
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="copyEmbedCode"
                                                class="gap-2"
                                            >
                                                <Copy class="h-4 w-4" />
                                                Copy Code
                                            </Button>
                                        </div>
                                    </div>
                                    <div class="bg-background border rounded p-3">
                                        <pre class="text-xs text-foreground overflow-x-auto whitespace-pre-wrap"><code v-text="embedCode"></code></pre>
                                    </div>
                                </div>

                                <!-- Iframe Options -->
                                <div class="space-y-4 pt-4 border-t">
                                    <h4 class="text-sm font-medium text-foreground">Iframe Options</h4>

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div class="flex items-center space-x-2">
                                            <Switch
                                                id="allow_fullscreen"
                                                :model-value="iframeOptions.allowFullscreen"
                                                @update:model-value="(value: any) => iframeOptions.allowFullscreen = value"
                                            />
                                            <Label for="allow_fullscreen" class="text-sm">Allow fullscreen</Label>
                                        </div>

                                        <div class="flex items-center space-x-2">
                                            <Switch
                                                id="loading_lazy"
                                                :model-value="iframeOptions.loading"
                                                @update:model-value="(value: any) => iframeOptions.loading = value"
                                            />
                                            <Label for="loading_lazy" class="text-sm">Lazy loading</Label>
                                        </div>


                                    </div>

                                    <!-- Embed-specific Settings -->
                                    <div class="space-y-4 pt-4 border-t">
                                        <h4 class="text-sm font-medium text-foreground">Chat Interface Options</h4>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <div class="flex items-center space-x-2">
                                                <Switch
                                                    id="remove_sidebar"
                                                    :model-value="embedForm.remove_sidebar"
                                                    @update:model-value="(value: any) => updateSetting('remove_sidebar', value)"
                                                />
                                                <Label for="remove_sidebar" class="text-sm">Remove sidebar</Label>
                                            </div>

                                            <div class="space-y-1">
                                                <div class="flex items-center space-x-2">
                                                    <Switch
                                                        id="hide_sidebar_on_page_load"
                                                        :model-value="embedForm.hide_sidebar_on_page_load && !embedForm.remove_sidebar"
                                                        @update:model-value="(value: any) => updateSetting('hide_sidebar_on_page_load', value)"
                                                        :disabled="embedForm.remove_sidebar"
                                                    />
                                                    <Label for="hide_sidebar_on_page_load" :class="['text-sm', { 'text-muted-foreground': embedForm.remove_sidebar }]">Hide Sidebar on page loading</Label>
                                                </div>
                                                <p v-if="embedForm.remove_sidebar" class="text-xs text-muted-foreground ml-6">
                                                    Disabled because sidebar is completely removed
                                                </p>
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <Switch
                                                    id="enable_chat_expand"
                                                    :model-value="embedForm.enable_chat_expand"
                                                    @update:model-value="(value: any) => updateSetting('enable_chat_expand', value)"
                                                />
                                                <Label for="enable_chat_expand" class="text-sm">Enable chat expand</Label>
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <Switch
                                                    id="enable_theme_switcher"
                                                    :model-value="embedForm.enable_theme_switcher"
                                                    @update:model-value="(value: any) => updateSetting('enable_theme_switcher', value)"
                                                />
                                                <Label for="enable_theme_switcher" class="text-sm">Enable theme switcher</Label>
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

                                    <!-- Customization Hint -->
                                    <Alert class="mt-6">
                                        <Info class="h-4 w-4" />
                                        <AlertTitle>Heads up!</AlertTitle>
                                        <AlertDescription>
                                            <Link :href="route('agents.appearance', { agent: agent.id })" class="font-medium text-primary hover:underline">For colors, themes, and logos visit Appearance</Link>.
                                        </AlertDescription>
                                    </Alert>
                                </div>
                            </div>
                        </div>


                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Preview Modal -->
        <PreviewModal
            v-model="showPreviewModal"
            preview-type="embed"
            :preview-url="buildIframeUrl(props.agent.chat_embed_url)"
            iframe-title="Chat Preview"
        />

    </AgentLayout>
</template>

<script setup lang="ts">
import { computed, ref, reactive } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import PreviewModal from '@/components/PreviewModal.vue';

import { Eye, Copy, Info } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    agent: any;
    embed_settings?: any;
    auth_token?: string | null;
}

const props = defineProps<Props>();

// States
const embedKey = ref(0);
const showPreviewModal = ref(false);

// Iframe options for display
const iframeOptions = reactive({
    width: '100%',
    height: '600px',
    border: 'none',
    allowFullscreen: true,
    loading: true,
    title: `Chat with ${props.agent.name}`,
});

// Embed settings form
const embedForm = useForm({
    remove_sidebar: props.embed_settings?.remove_sidebar ?? false,
    hide_sidebar_on_page_load: props.embed_settings?.hide_sidebar_on_page_load ?? false,
    enable_theme_switcher: props.embed_settings?.enable_theme_switcher ?? true,
    default_theme: props.embed_settings?.default_theme ?? 'auto',
    enable_chat_expand: props.embed_settings?.enable_chat_expand ?? true,
});

// Update setting method
const updateSetting = (key: string, value: any) => {
    (embedForm as any)[key] = value;

    // If remove_sidebar is enabled, automatically disable hide_sidebar_on_page_load
    let dataToSend: any = { [key]: value };

    if (key === 'remove_sidebar' && value === true) {
        (embedForm as any).hide_sidebar_on_page_load = false;
        dataToSend.hide_sidebar_on_page_load = false;
    }

    // Always send POST request with the true/false value
    router.post(route('agents.settings.update', {
        agent: props.agent.id,
        group: 'embed'
    }), dataToSend, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Setting updated successfully');
        },
        onError: () => {
            toast.error('Failed to update setting');
        }
    });
};

// Computed embed code
const embedCode = computed(() => {
    const attributes = [
        `src="${props.agent.chat_embed_url}"`, // Use chat_embed_url from agent with auth token
        `width="${iframeOptions.width}"`,
        `height="${iframeOptions.height}"`,
        `title="${iframeOptions.title}"`,
        `frameborder="0"`,
    ];

    // Add border style if not 'none'
    if (iframeOptions.border !== 'none') {
        attributes.push(`style="border: ${iframeOptions.border}"`);
    }

    // Add allowfullscreen if enabled - only when true
    if (iframeOptions.allowFullscreen === true) {
        attributes.push('allowfullscreen');
    }

    // Add lazy loading if enabled - only when true
    if (iframeOptions.loading === true) {
        attributes.push('loading="lazy"');
    }

    return `<iframe ${attributes.join(' ')}></iframe>`;
});

// Methods
const buildIframeUrl = (baseUrl: string) => {
    if (props.auth_token) {
        const separator = baseUrl.includes('?') ? '&' : '?';
        return `${baseUrl}${separator}auth_token=${props.auth_token}`;
    }
    return baseUrl;
};

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


</script>
