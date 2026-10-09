/**
 * The agent's YouTube videos on the YouTube page: loads the list, retrains a
 * video, and deletes one behind a confirmation dialog, with a toast for each
 * outcome.
 */
import { deleteYoutubeVideo, fetchYoutubeVideos, retrainYoutubeVideo, type YoutubeVideo } from '@/api/youtubeSources';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

export function useYoutubeVideos(agentId: string) {
    const { t } = useI18n();

    const videos = ref<YoutubeVideo[]>([]);
    const isLoading = ref(false);
    /** True until the first load finished; the spinner only shows then. */
    const isInitialLoad = ref(true);

    const isDeleteDialogOpen = ref(false);
    const videoToDelete = ref<YoutubeVideo | null>(null);

    async function loadVideos(): Promise<void> {
        try {
            isLoading.value = true;
            videos.value = await fetchYoutubeVideos(agentId);
        } catch {
            toast.error(t('agents.sources.youtube.load_failed'));
        } finally {
            isLoading.value = false;
            isInitialLoad.value = false;
        }
    }

    async function retrainVideo(video: YoutubeVideo): Promise<void> {
        try {
            await retrainYoutubeVideo(agentId, video.id);
            toast.success(t('agents.sources.youtube.retrain_started'));
            await loadVideos();
        } catch {
            toast.error(t('agents.sources.youtube.retrain_failed'));
        }
    }

    function confirmDelete(video: YoutubeVideo): void {
        videoToDelete.value = video;
        isDeleteDialogOpen.value = true;
    }

    async function deleteVideo(): Promise<void> {
        if (!videoToDelete.value) {
            return;
        }

        try {
            await deleteYoutubeVideo(agentId, videoToDelete.value.id);
            toast.success(t('agents.sources.youtube.deleted'));
            await loadVideos();
            isDeleteDialogOpen.value = false;
            videoToDelete.value = null;
        } catch {
            toast.error(t('agents.sources.youtube.delete_failed'));
        }
    }

    return { videos, isLoading, isInitialLoad, loadVideos, retrainVideo, isDeleteDialogOpen, confirmDelete, deleteVideo };
}
