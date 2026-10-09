<script setup lang="ts">
/**
 * The agent's YouTube sources (behind the `youtube_sources` feature flag): add
 * a video by its URL, see each video with its training status, and retrain or
 * delete it.
 */
import YoutubeVideoRow from '@/components/agents/sources/youtube/YoutubeVideoRow.vue';
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useYoutubeVideos } from '@/composables/agents/sources/useYoutubeVideos';
import { LoaderCircle, Plus, Youtube } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
}>();

const { t } = useI18n();

const {
    videos,
    isLoading,
    isInitialLoad,
    loadVideos,
    newUrl,
    isAdding,
    urlError,
    addVideo,
    retrainVideo,
    isDeleteDialogOpen,
    confirmDelete,
    deleteVideo,
} = useYoutubeVideos(props.agent.external_id);

onMounted(loadVideos);
</script>

<template>
    <div class="flex justify-center">
        <div class="w-full max-w-6xl space-y-6">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Youtube class="h-5 w-5 text-red-500" />
                        <CardTitle>{{ t('agents.sources.youtube.add_title') }}</CardTitle>
                    </div>
                    <CardDescription>
                        {{ t('agents.sources.youtube.add_description') }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="addVideo">
                        <div class="space-y-2">
                            <Label for="youtube-url">{{ t('agents.sources.youtube.url_label') }}</Label>
                            <div class="flex gap-2">
                                <Input
                                    id="youtube-url"
                                    v-model="newUrl"
                                    type="url"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                    :disabled="isAdding"
                                    class="flex-1"
                                />
                                <Button type="submit" :disabled="isAdding || !newUrl">
                                    <LoaderCircle v-if="isAdding" class="me-2 h-4 w-4 animate-spin" />
                                    <Plus v-else class="me-2 h-4 w-4" />
                                    {{ t('agents.sources.youtube.add_video') }}
                                </Button>
                            </div>
                            <InputError :message="urlError" />
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ t('agents.sources.youtube.list_title', { count: videos.length }) }}</CardTitle>
                    <CardDescription>{{ t('agents.sources.youtube.list_description') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="isLoading && isInitialLoad" class="flex items-center justify-center py-12">
                        <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
                    </div>

                    <div v-else-if="videos.length === 0" class="py-12 text-center">
                        <Youtube class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <h3 class="mb-2 text-lg font-medium">{{ t('agents.sources.youtube.empty_title') }}</h3>
                        <p class="text-muted-foreground text-sm">{{ t('agents.sources.youtube.empty_description') }}</p>
                    </div>

                    <ScrollArea v-else class="h-[500px] pe-4">
                        <div class="space-y-4">
                            <YoutubeVideoRow
                                v-for="video in videos"
                                :key="video.id"
                                :video="video"
                                @retrain="retrainVideo(video)"
                                @delete="confirmDelete(video)"
                            />
                        </div>
                    </ScrollArea>
                </CardContent>
            </Card>

            <DeleteConfirmationDialog
                v-model:open="isDeleteDialogOpen"
                :title="t('agents.sources.youtube.delete_title')"
                :description="t('agents.sources.youtube.delete_description')"
                @confirm="deleteVideo"
            />
        </div>
    </div>
</template>
