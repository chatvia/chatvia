<template>
    <AgentLayout :agent="agent" title="Preview">
        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="flex items-center gap-2">
                                <BotIcon class="h-5 w-5" />
                                Chat
                            </CardTitle>
                            <CardDescription>
                                Start chatting with the agent
                            </CardDescription>
                        </div>

                        <!-- Actions Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm" class="gap-2">
                                    <MoreHorizontal class="h-4 w-4" />
                                    Actions
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="showPreviewModal = true">
                                    <BotIcon class="mr-2 h-4 w-4" />
                                    Preview
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="openFullPreview">
                                    <ExternalLink class="mr-2 h-4 w-4" />
                                    Open in New Tab
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="copyUrl">
                                    <Copy class="mr-2 h-4 w-4" />
                                    Copy URL
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Preview Container -->
                    <div class="bg-muted rounded-lg p-6">
                        <div class="flex justify-center">
                            <div class="w-full max-w-4xl h-[600px] border rounded-lg overflow-hidden bg-white shadow-lg">
                                <iframe
                                    :src="buildIframeUrl(props.agent.chat_url)"
                                    class="w-full h-full border-0"
                                    title="Chat Preview"
                                    loading="lazy"
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
            preview-type="embed"
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
    agent: any;
    authToken?: string | null;
}

const props = defineProps<Props>();

// States
const showPreviewModal = ref(false);

// Methods
const buildIframeUrl = (baseUrl: string) => {
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
