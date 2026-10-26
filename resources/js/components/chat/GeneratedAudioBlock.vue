<script setup lang="ts">
/**
 * An audio file the agent made for the chatter. While its background job
 * runs it shows a placeholder; once made, a player and a download link; if
 * it could not be made, a short reason. A component, not markdown: the
 * answer's HTML sanitizer drops `<audio>`.
 */
import type { GeneratedMediaArtifactData } from '@/types/generatedMedia';
import { AudioLines, Download, VolumeX } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    media: GeneratedMediaArtifactData;
}>();

const { t } = useI18n();

const failureText = computed(() => {
    if (props.media.failure === 'refused') return t('chat.generated_media.refused');
    if (props.media.failure === 'timed_out') return t('chat.generated_media.timed_out');
    return t('chat.generated_media.audio_failed');
});
</script>

<template>
    <div class="generated-audio-block not-prose my-3 max-w-md">
        <div v-if="media.status === 'ready' && media.url" class="bg-card flex items-center gap-2 rounded-md border p-2">
            <audio :src="media.url" controls preload="metadata" class="h-10 min-w-0 flex-1" />
            <a
                :href="media.url"
                download
                class="text-muted-foreground hover:text-foreground inline-flex shrink-0 items-center rounded-md p-2"
                :aria-label="t('chat.generated_media.download')"
                :title="t('chat.generated_media.download')"
            >
                <Download class="h-4 w-4" aria-hidden="true" />
            </a>
        </div>

        <div
            v-else-if="media.status === 'failed'"
            class="bg-card text-muted-foreground flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
            role="status"
        >
            <VolumeX class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ failureText }}
        </div>

        <div v-else class="bg-muted text-muted-foreground flex h-14 items-center gap-2 rounded-md border px-3 text-sm" role="status">
            <AudioLines class="h-4 w-4 shrink-0 animate-pulse" aria-hidden="true" />
            {{ t('chat.generated_media.audio_pending') }}
        </div>
    </div>
</template>
