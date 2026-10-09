<script setup lang="ts">
/**
 * Playback of a finished voice note before it is saved: play/pause, a seekable
 * progress bar and elapsed / total time.
 */
import { Button } from '@/components/ui/button';
import { CheckCircle, Pause, Play } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    /** Object URL of the recorded audio. */
    audioUrl: string;
    /** Recorder's own "m:ss" duration, shown until the audio element is ready. */
    fallbackDuration: string;
}>();

const { t } = useI18n();

const isPlaying = ref(false);
const playbackProgress = ref(0);
const audioElement = ref<HTMLAudioElement | null>(null);
const progressBarRef = ref<HTMLDivElement | null>(null);

const togglePlayback = async () => {
    if (!audioElement.value) return;

    if (isPlaying.value) {
        audioElement.value.pause();
    } else {
        // If at the end, restart from beginning
        if (audioElement.value.ended || audioElement.value.currentTime >= audioElement.value.duration) {
            audioElement.value.currentTime = 0;
        }
        try {
            await audioElement.value.play();
        } catch (err) {
            console.error('Playback failed:', err);
        }
    }
};

const handleAudioLoaded = () => {
    // Audio metadata is loaded, duration is now available
    if (audioElement.value) {
        playbackProgress.value = 0;
    }
};

const handleAudioPlay = () => {
    isPlaying.value = true;
};

const handleAudioPause = () => {
    isPlaying.value = false;
};

const handleAudioEnded = () => {
    isPlaying.value = false;
    playbackProgress.value = 0;
    // Reset to beginning so it can be played again
    if (audioElement.value) {
        audioElement.value.currentTime = 0;
    }
};

const handleAudioTimeUpdate = () => {
    if (audioElement.value) {
        playbackProgress.value = (audioElement.value.currentTime / audioElement.value.duration) * 100;
    }
};

const handleSeek = (event: MouseEvent) => {
    if (!audioElement.value || !progressBarRef.value) return;

    const duration = audioElement.value.duration;
    if (!duration || !Number.isFinite(duration) || duration <= 0) return;

    const rect = progressBarRef.value.getBoundingClientRect();
    if (rect.width <= 0) return;

    const clickX = event.clientX - rect.left;
    const percentage = Math.max(0, Math.min(1, clickX / rect.width));
    const newTime = percentage * duration;

    if (Number.isFinite(newTime)) {
        audioElement.value.currentTime = newTime;
        playbackProgress.value = percentage * 100;
    }
};

const formatPlaybackTime = (seconds: number) => {
    if (!Number.isFinite(seconds) || seconds < 0) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};
</script>

<template>
    <div class="bg-muted/50 rounded-lg border p-4">
        <div class="flex items-center gap-4">
            <Button
                @click="togglePlayback"
                variant="outline"
                size="icon"
                :aria-label="isPlaying ? t('shared.voice_recorder.pause_playback') : t('shared.voice_recorder.play_recording')"
            >
                <Pause v-if="isPlaying" class="h-4 w-4" />
                <Play v-else class="h-4 w-4" />
            </Button>

            <div class="flex-1 space-y-1">
                <div ref="progressBarRef" class="bg-muted group relative h-2 cursor-pointer overflow-hidden rounded-full" @click="handleSeek">
                    <div class="bg-primary h-full transition-all duration-100" :style="{ width: `${playbackProgress}%` }" />
                    <!-- Seek indicator -->
                    <div
                        class="bg-primary absolute top-1/2 h-3 w-3 -translate-y-1/2 rounded-full opacity-0 shadow-sm transition-opacity group-hover:opacity-100"
                        :style="{ left: `calc(${playbackProgress}% - 6px)` }"
                    />
                </div>
                <div class="text-muted-foreground flex justify-between text-xs">
                    <span class="font-mono">
                        {{ audioElement ? formatPlaybackTime(audioElement.currentTime) : '0:00' }}
                    </span>
                    <span class="font-mono">
                        {{ audioElement ? formatPlaybackTime(audioElement.duration || 0) : fallbackDuration }}
                    </span>
                </div>
            </div>

            <div class="text-muted-foreground flex items-center gap-1.5 text-sm">
                <CheckCircle class="h-4 w-4 text-green-600" />
                <span>{{ t('shared.voice_recorder.recorded') }}</span>
            </div>
        </div>

        <audio
            ref="audioElement"
            :src="audioUrl"
            preload="metadata"
            @loadedmetadata="handleAudioLoaded"
            @play="handleAudioPlay"
            @pause="handleAudioPause"
            @ended="handleAudioEnded"
            @timeupdate="handleAudioTimeUpdate"
        />
    </div>
</template>
