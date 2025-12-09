<template>
    <AgentLayout :agent="agent" title="Playground">
        <template #header-actions>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="showPreviewModal = true"
                    class="gap-2"
                >
                    <BotIcon class="h-4 w-4" />
                    <span class="hidden sm:inline">Preview</span>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="openFullPreview"
                    class="gap-2"
                >
                    <ExternalLink class="h-4 w-4" />
                    <span class="hidden sm:inline">Open</span>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="copyUrl"
                    class="gap-2"
                >
                    <Copy class="h-4 w-4" />
                    <span class="hidden sm:inline">Copy</span>
                </Button>
            </div>
        </template>
        <div class="space-y-6">
            <Card>

                <CardContent>
                    <!-- Preview Container -->
                    <div class="bg-muted rounded-lg p-6">
                        <div class="flex justify-center">
                            <div class="w-full h-[800px] border rounded-lg overflow-hidden bg-white shadow-lg">
                                <iframe
                                    :src="buildIframeUrl(props.agent.chat_url)"
                                    class="w-full h-full border-0"
                                    title="Chat Preview"
                                    loading="lazy"
                                    sandbox="allow-scripts allow-same-origin allow-forms"
                                ></iframe>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Preview Modal -->
        <PreviewModal
            v-model="showPreviewModal"
            :preview-type="props.agent.type === 'widget' ? 'widget' : 'embed'"
            :preview-url="buildIframeUrl(props.agent.chat_url)"
            iframe-title="Chat Preview"
        />
    </AgentLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import PreviewModal from '@/components/PreviewModal.vue';
import { BotIcon, ExternalLink, Copy, MoreHorizontal } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

interface Props {
    agent: App.Data.Resources.Agent.AgentResourceData;
    authToken?: string | null;
}

const props = defineProps<Props>();

// States
const showPreviewModal = ref(false);

// Methods
const buildIframeUrl = (baseUrl: string) => {
    // Use widget preview route for widget agents
    if (props.agent.type === 'widget') {
        return route('agents.widget.preview', { agent: props.agent.external_id });
    }

    // Use regular chat URL with auth token for other agents
    if (props.authToken) {
        const separator = baseUrl.includes('?') ? '&' : '?';
        return `${baseUrl}${separator}auth_token=${props.authToken}`;
    }
    return baseUrl;
};

const openFullPreview = () => {
    window.open(buildIframeUrl(props.agent.chat_url), '_blank');
};

const copyUrl = async () => {
    try {
        await navigator.clipboard.writeText(buildIframeUrl(props.agent.chat_url));
        toast.success('URL copied to clipboard!');
    } catch (error) {
        console.error('Failed to copy URL:', error);
        toast.error('Failed to copy URL. Please try again.');
    }
};
</script>
