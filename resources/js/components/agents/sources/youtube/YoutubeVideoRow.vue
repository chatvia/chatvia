<script setup lang="ts">
/**
 * One YouTube video in the list: thumbnail, title and link, status badge,
 * the failure reason when training failed, and the retrain / delete buttons.
 */
import type { YoutubeVideo, YoutubeVideoStatus } from '@/api/youtubeSources';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useDateFormat } from '@/composables/useDateFormat';
import { AlertCircle, ExternalLink, LoaderCircle, Trash2, Youtube } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    video: YoutubeVideo;
}>();

const emit = defineEmits<{
    retrain: [];
    delete: [];
}>();

const { t } = useI18n();

// Dates follow the user's chosen format (Settings → Date & time), with a 24h time.
const { formatDateTime } = useDateFormat();

const canRetrain = computed(() => props.video.status === 'ingested' || props.video.status === 'failed');

const statusVariant = computed((): 'default' | 'secondary' | 'destructive' => {
    switch (props.video.status) {
        case 'ingested':
            return 'default';
        case 'failed':
            return 'destructive';
        default:
            return 'secondary';
    }
});

const statusLabel = computed((): string => {
    const status: YoutubeVideoStatus = props.video.status;

    switch (status) {
        case 'ingested':
            return t('agents.sources.youtube.status.completed');
        case 'ingesting':
            return t('agents.sources.youtube.status.processing');
        case 'pending':
            return t('agents.sources.youtube.status.pending');
        case 'failed':
            return t('agents.sources.youtube.status.failed');
        default:
            return status;
    }
});

/** The video's thumbnail, when the URL holds a recognisable YouTube video id. */
const thumbnailUrl = computed((): string | null => {
    const regex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
    const videoId = props.video.url.match(regex)?.[1];

    return videoId ? `https://img.youtube.com/vi/${videoId}/mqdefault.jpg` : null;
});
</script>

<template>
    <div class="hover:bg-accent/50 rounded-lg border p-4 transition-colors">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <img
                    v-if="thumbnailUrl"
                    :src="thumbnailUrl"
                    :alt="video.title || t('agents.sources.youtube.video_alt')"
                    class="h-20 w-32 rounded object-cover"
                />
                <div v-else class="bg-muted flex h-20 w-32 items-center justify-center rounded">
                    <Youtube class="text-muted-foreground h-8 w-8" />
                </div>
            </div>

            <div class="min-w-0 flex-1">
                <div class="mb-2 flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1">
                        <h4 class="mb-1 truncate font-medium">
                            {{ video.title || t('agents.sources.youtube.video_fallback_title') }}
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
                    <Badge :variant="statusVariant">
                        {{ statusLabel }}
                    </Badge>
                </div>

                <div class="text-muted-foreground mb-3 text-xs">
                    {{ t('agents.sources.youtube.added_at', { date: formatDateTime(video.created_at) }) }}
                </div>

                <Alert v-if="video.status === 'failed' && video.failure_reason" variant="destructive" class="mb-3">
                    <AlertCircle class="h-4 w-4" />
                    <AlertDescription>
                        {{ video.failure_reason }}
                    </AlertDescription>
                </Alert>

                <div class="flex gap-2">
                    <Button v-if="canRetrain" variant="outline" size="sm" @click="emit('retrain')">
                        <LoaderCircle class="me-2 h-3 w-3" />
                        {{ t('agents.sources.youtube.retrain') }}
                    </Button>
                    <Button variant="destructive" size="sm" @click="emit('delete')">
                        <Trash2 class="me-2 h-3 w-3" />
                        {{ t('agents.sources.common.delete') }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
