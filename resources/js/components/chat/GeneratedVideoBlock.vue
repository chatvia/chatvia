<script setup lang="ts">
/**
 * A short video the agent made for the chatter. A video takes minutes, so
 * while its background job runs it shows a placeholder that says so; once
 * made, a player and a download link; if it could not be made, a short
 * reason. A component, not markdown: the answer's HTML sanitizer drops
 * `<video>`.
 */
import type { GeneratedMediaArtifactData } from '@/types/generatedMedia';
import { Clapperboard, Download, VideoOff } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    media: GeneratedMediaArtifactData;
}>();

const { t } = useI18n();

const label = computed(() =>
    props.media.prompt ? t('chat.generated_media.video_alt', { prompt: props.media.prompt }) : t('chat.generated_media.video'),
);

const failureText = computed(() => {
    if (props.media.failure === 'refused') return t('chat.generated_media.refused');
    if (props.media.failure === 'timed_out') return t('chat.generated_media.timed_out');
    return t('chat.generated_media.video_failed');
});
</script>

<template>
    <div class="generated-video-block not-prose my-3 max-w-md">
        <div v-if="media.status === 'ready' && media.url" class="group bg-card relative overflow-hidden rounded-md border">
            <video :src="media.url" :aria-label="label" controls playsinline preload="metadata" class="block h-auto w-full bg-black" />
            <a
                :href="media.url"
                download
                class="bg-background/90 text-foreground absolute end-2 top-2 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs opacity-0 shadow-sm transition-opacity group-hover:opacity-100 focus:opacity-100"
            >
                <Download class="h-3.5 w-3.5" aria-hidden="true" />
                {{ t('chat.generated_media.download') }}
            </a>
        </div>

        <div
            v-else-if="media.status === 'failed'"
            class="bg-card text-muted-foreground flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
            role="status"
        >
            <VideoOff class="h-4 w-4 shrink-0" aria-hidden="true" />
            {{ failureText }}
        </div>

        <div
            v-else
            class="bg-muted text-muted-foreground flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-md border px-4 text-center text-sm"
            role="status"
        >
            <Clapperboard class="h-5 w-5 animate-pulse" aria-hidden="true" />
            {{ t('chat.generated_media.video_pending') }}
        </div>
    </div>
</template>
