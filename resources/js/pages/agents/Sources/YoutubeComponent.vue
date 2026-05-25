<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertCircle, ExternalLink, LoaderCircle, Plus, Trash2, Youtube } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    agent: Domain.Agents.Data.Resources.AgentResourceData;
}

interface YoutubeVideo {
    id: number;
    url: string;
    title?: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    created_at: string;
    error_message?: string;
}

const props = defineProps<Props>();

// State
const youtubeVideos = ref<YoutubeVideo[]>([]);
const isLoadingVideos = ref(false);
const isInitialLoad = ref(true);

// Form state using Inertia
const youtubeForm = useForm({
    url: '',
});

// Delete confirmation dialog
const showDeleteDialog = ref(false);
const videoToDelete = ref<YoutubeVideo | null>(null);

// Load YouTube videos
const loadYoutubeVideos = async () => {
    try {
        isLoadingVideos.value = true;
        const response = await axios.get(route('agents.sources.youtube.index', { agent: props.agent.external_id }));
        youtubeVideos.value = response.data;
    } catch (error) {
        console.error('Failed to load YouTube videos:', error);
        toast.error('Failed to load YouTube videos');
    } finally {
        isLoadingVideos.value = false;
        isInitialLoad.value = false;
    }
};

// Submit YouTube URL
const submitYoutubeUrl = () => {
    youtubeForm.post(route('agents.sources.youtube.store', { agent: props.agent.external_id }), {
        preserveScroll: true,
        onSuccess: () => {
            youtubeForm.reset();
            toast.success('YouTube video added successfully');
            loadYoutubeVideos();
        },
        onError: (errors) => {
            console.error('Error submitting YouTube URL:', errors);
            if (errors.url) {
                toast.error(errors.url[0]);
            } else {
                toast.error('Failed to add YouTube video');
            }
        },
    });
};

// Delete video
const confirmDelete = (video: YoutubeVideo) => {
    videoToDelete.value = video;
    showDeleteDialog.value = true;
};

const deleteVideo = async () => {
    if (!videoToDelete.value) return;

    try {
        await axios.delete(
            route('agents.sources.youtube.destroy', {
                agent: props.agent.external_id,
                youtube: videoToDelete.value.id,
            }),
        );

        toast.success('YouTube video deleted successfully');
        await loadYoutubeVideos();
        showDeleteDialog.value = false;
        videoToDelete.value = null;
    } catch (error) {
        console.error('Failed to delete video:', error);
        toast.error('Failed to delete video');
    }
};

// Retrain video
const retrainVideo = async (video: YoutubeVideo) => {
    try {
        await axios.post(
            route('agents.sources.youtube.retrain', {
                agent: props.agent.external_id,
                youtube: video.id,
            }),
        );

        toast.success('YouTube video retraining started');
        await loadYoutubeVideos();
    } catch (error) {
        console.error('Failed to retrain video:', error);
        toast.error('Failed to retrain video');
    }
};

// Get status badge variant
const getStatusVariant = (status: string) => {
    switch (status) {
        case 'completed':
            return 'default';
        case 'processing':
        case 'pending':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
};

// Get status label
const getStatusLabel = (status: string) => {
    switch (status) {
        case 'completed':
            return 'Completed';
        case 'processing':
            return 'Processing';
        case 'pending':
            return 'Pending';
        case 'failed':
            return 'Failed';
        default:
            return status;
    }
};

// Extract video ID from URL for thumbnail
const getVideoId = (url: string): string | null => {
    const regex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
    const match = url.match(regex);
    return match ? match[1] : null;
};

const getThumbnailUrl = (url: string): string | null => {
    const videoId = getVideoId(url);
    return videoId ? `https://img.youtube.com/vi/${videoId}/mqdefault.jpg` : null;
};

// Format date
const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

// Load videos on mount
onMounted(() => {
    loadYoutubeVideos();
});
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <!-- Add YouTube Video Form -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Youtube class="h-5 w-5 text-red-500" />
                        <CardTitle>Add YouTube Video</CardTitle>
                    </div>
                    <CardDescription>
                        Enter a YouTube video URL to extract its transcript and add it to your agent's knowledge base.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitYoutubeUrl" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="youtube-url">YouTube URL</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="youtube-url"
                                    v-model="youtubeForm.url"
                                    type="url"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                    :disabled="youtubeForm.processing"
                                    class="flex-1"
                                />
                                <Button type="submit" :disabled="youtubeForm.processing || !youtubeForm.url">
                                    <LoaderCircle v-if="youtubeForm.processing" class="mr-2 h-4 w-4 animate-spin" />
                                    <Plus v-else class="mr-2 h-4 w-4" />
                                    Add Video
                                </Button>
                            </div>
                            <InputError :message="youtubeForm.errors.url" />
                        </div>
                    </form>
                </CardContent>
            </Card>

            <!-- YouTube Videos List -->
            <Card>
                <CardHeader>
                    <CardTitle>YouTube Videos ({{ youtubeVideos.length }})</CardTitle>
                    <CardDescription> Manage YouTube videos in your agent's knowledge base. </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Loading State -->
                    <div v-if="isLoadingVideos && isInitialLoad" class="flex items-center justify-center py-12">
                        <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="youtubeVideos.length === 0" class="py-12 text-center">
                        <Youtube class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <h3 class="mb-2 text-lg font-medium">No YouTube videos yet</h3>
                        <p class="text-muted-foreground text-sm">Add your first YouTube video to get started.</p>
                    </div>

                    <!-- Videos List -->
                    <ScrollArea v-else class="h-[500px] pr-4">
                        <div class="space-y-4">
                            <div v-for="video in youtubeVideos" :key="video.id" class="hover:bg-accent/50 rounded-lg border p-4 transition-colors">
                                <div class="flex items-start gap-4">
                                    <!-- Thumbnail -->
                                    <div class="flex-shrink-0">
                                        <img
                                            v-if="getThumbnailUrl(video.url)"
                                            :src="getThumbnailUrl(video.url)!"
                                            :alt="video.title || 'YouTube video'"
                                            class="h-20 w-32 rounded object-cover"
                                        />
                                        <div v-else class="bg-muted flex h-20 w-32 items-center justify-center rounded">
                                            <Youtube class="text-muted-foreground h-8 w-8" />
                                        </div>
                                    </div>

                                    <!-- Content -->
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-2 flex items-start justify-between gap-2">
                                            <div class="min-w-0 flex-1">
                                                <h4 class="mb-1 truncate font-medium">
                                                    {{ video.title || 'YouTube Video' }}
                                                </h4>
                                                <a
                                                    :href="video.url"
                                                    target="_blank"
                                                    class="text-muted-foreground hover:text-foreground flex items-center gap-1 truncate text-sm"
                                                >
                                                    <span class="truncate">{{ video.url }}</span>
                                                    <ExternalLink class="h-3 w-3 flex-shrink-0" />
                                                </a>
                                            </div>
                                            <Badge :variant="getStatusVariant(video.status)">
                                                {{ getStatusLabel(video.status) }}
                                            </Badge>
                                        </div>

                                        <div class="text-muted-foreground mb-3 text-xs">Added {{ formatDate(video.created_at) }}</div>

                                        <!-- Error Message -->
                                        <Alert v-if="video.status === 'failed' && video.error_message" variant="destructive" class="mb-3">
                                            <AlertCircle class="h-4 w-4" />
                                            <AlertDescription>
                                                {{ video.error_message }}
                                            </AlertDescription>
                                        </Alert>

                                        <!-- Actions -->
                                        <div class="flex gap-2">
                                            <Button
                                                v-if="video.status === 'completed' || video.status === 'failed'"
                                                variant="outline"
                                                size="sm"
                                                @click="retrainVideo(video)"
                                            >
                                                <LoaderCircle class="mr-2 h-3 w-3" />
                                                Retrain
                                            </Button>
                                            <Button variant="destructive" size="sm" @click="confirmDelete(video)">
                                                <Trash2 class="mr-2 h-3 w-3" />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </ScrollArea>
                </CardContent>
            </Card>

            <!-- Delete Confirmation Dialog -->
            <DeleteConfirmationDialog
                v-model:open="showDeleteDialog"
                title="Delete YouTube Video"
                :description="`Are you sure you want to delete this YouTube video? This will remove it from your agent's knowledge base.`"
                @confirm="deleteVideo"
            />
        </div>
    </div>
</template>
