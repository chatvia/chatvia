<script setup lang="ts">
/**
 * Voice-note recorder for knowledge sources: record (with pause/resume and a
 * live level meter, capped at `maxDuration`), preview, name and hand the audio
 * to the parent via `recorded`.
 */
import RecordingPlayback from '@/components/voice-recorder/RecordingPlayback.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSourceVoiceRecorder, type RecordingData } from '@/composables/useSourceVoiceRecorder';
import { AlertCircle, Mic, Pause, Play, Square, Trash2, Upload } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    title?: string;
    description?: string;
    maxDuration?: number; // in seconds
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    maxDuration: 600, // 10 minutes default
    disabled: false,
});

const { t } = useI18n();

const displayTitle = computed(() => props.title ?? t('shared.voice_recorder.title_default'));
const displayDescription = computed(() => props.description ?? t('shared.voice_recorder.description_default'));

const emit = defineEmits<{
    (e: 'recorded', data: { blob: Blob; name: string; duration: number }): void;
    (e: 'cancel'): void;
}>();

const {
    isRecording,
    isPaused,
    isSupported,
    error,
    formattedDuration,
    audioLevel,
    startRecording,
    pauseRecording,
    resumeRecording,
    stopRecording,
    cancelRecording,
    recordingDuration,
} = useSourceVoiceRecorder();

// States
const recordingData = ref<RecordingData | null>(null);
const recordingName = ref('');
const audioUrl = ref<string | null>(null);

// Auto-stop recording when max duration is reached
watch(recordingDuration, (duration) => {
    if (duration >= props.maxDuration * 1000) {
        handleStopRecording();
    }
});

const remainingTime = computed(() => {
    const remaining = Math.max(0, props.maxDuration - Math.floor(recordingDuration.value / 1000));
    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;
    return `${minutes}:${seconds.toString().padStart(2, '0')}`;
});

const recordingProgress = computed(() => {
    return Math.min(100, (recordingDuration.value / (props.maxDuration * 1000)) * 100);
});

const maxDurationMinutes = computed(() => Math.floor(props.maxDuration / 60));

// Waveform bars (12 bars for visualization)
const waveformBars = computed(() => {
    const bars = [];
    const numBars = 12;
    for (let i = 0; i < numBars; i++) {
        const baseHeight = isRecording.value && !isPaused.value ? 0.2 + audioLevel.value * 0.8 * Math.sin(Date.now() / 100 + i * 0.5) : 0.15;
        const height = Math.max(0.1, Math.min(1, baseHeight + Math.random() * 0.1));
        bars.push(height);
    }
    return bars;
});

const handleStartRecording = async () => {
    recordingData.value = null;
    audioUrl.value = null;
    recordingName.value = '';
    await startRecording();
};

const handleStopRecording = async () => {
    const data = await stopRecording();
    if (data) {
        recordingData.value = data;
        audioUrl.value = URL.createObjectURL(data.blob);

        const now = new Date();
        const timestamp = now.toISOString().slice(0, 19).replace(/[T:]/g, '-');
        recordingName.value = `voice-recording-${timestamp}`;
    }
};

const handlePauseResume = () => {
    if (isPaused.value) {
        resumeRecording();
    } else {
        pauseRecording();
    }
};

const handleCancel = () => {
    cancelRecording();
    recordingData.value = null;
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }
    recordingName.value = '';
    emit('cancel');
};

const handleDiscard = () => {
    recordingData.value = null;
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }
    recordingName.value = '';
};

const handleSave = () => {
    if (!recordingData.value) return;

    const extension = recordingData.value.mimeType.includes('webm') ? 'webm' : 'm4a';
    const fileName = recordingName.value.trim() || 'voice-recording';

    emit('recorded', {
        blob: recordingData.value.blob,
        name: `${fileName}.${extension}`,
        duration: recordingData.value.duration,
    });

    recordingData.value = null;
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }
    recordingName.value = '';
};

onUnmounted(() => {
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
    }
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Mic class="h-5 w-5" />
                {{ displayTitle }}
            </CardTitle>
            <CardDescription>
                {{ displayDescription }}
            </CardDescription>
        </CardHeader>

        <CardContent class="space-y-6">
            <!-- Not Supported Alert -->
            <Alert v-if="!isSupported" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    {{ t('shared.voice_recorder.not_supported') }}
                </AlertDescription>
            </Alert>

            <!-- Error Alert -->
            <Alert v-if="error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <!-- Recording Interface -->
            <div v-if="isSupported && !recordingData" class="space-y-6">
                <!-- Waveform Visualization -->
                <div class="bg-muted/50 relative flex h-24 items-center justify-center rounded-lg border">
                    <!-- Waveform Bars -->
                    <div class="flex items-center justify-center gap-1">
                        <div
                            v-for="(height, index) in waveformBars"
                            :key="index"
                            class="w-1.5 rounded-full transition-all duration-75"
                            :class="isRecording && !isPaused ? 'bg-primary' : 'bg-muted-foreground/30'"
                            :style="{ height: `${Math.max(6, height * 60)}px` }"
                        />
                    </div>

                    <!-- Recording Indicator -->
                    <div
                        v-if="isRecording"
                        class="bg-background absolute bottom-2 left-1/2 flex -translate-x-1/2 items-center gap-2 rounded-full border px-3 py-1 text-sm"
                    >
                        <div class="h-2 w-2 rounded-full" :class="isPaused ? 'bg-amber-500' : 'animate-pulse bg-red-500'" />
                        <span class="font-mono tabular-nums">{{ formattedDuration }}</span>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div v-if="isRecording" class="space-y-1">
                    <div class="text-muted-foreground flex justify-between text-xs">
                        <span>{{ t('shared.voice_recorder.recording') }}</span>
                        <span>{{ t('shared.voice_recorder.remaining_time', { time: remainingTime }) }}</span>
                    </div>
                    <div class="bg-muted h-1 overflow-hidden rounded-full">
                        <div class="bg-primary h-full transition-all duration-300" :style="{ width: `${recordingProgress}%` }" />
                    </div>
                </div>

                <!-- Recording Controls -->
                <div class="flex items-center justify-center gap-3">
                    <template v-if="!isRecording">
                        <Button @click="handleStartRecording" :disabled="disabled">
                            <Mic class="me-2 h-4 w-4" />
                            {{ t('shared.voice_recorder.start_recording') }}
                        </Button>
                    </template>

                    <template v-else>
                        <Button
                            @click="handlePauseResume"
                            variant="outline"
                            size="icon"
                            :aria-label="isPaused ? t('shared.voice_recorder.resume_recording') : t('shared.voice_recorder.pause_recording')"
                        >
                            <Play v-if="isPaused" class="h-4 w-4" />
                            <Pause v-else class="h-4 w-4" />
                        </Button>

                        <Button @click="handleStopRecording">
                            <Square class="me-2 h-4 w-4" />
                            {{ t('shared.voice_recorder.stop') }}
                        </Button>

                        <Button @click="handleCancel" variant="outline" size="icon" :aria-label="t('shared.voice_recorder.cancel_recording')">
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </template>
                </div>
            </div>

            <!-- Preview & Save Interface -->
            <div v-if="recordingData && audioUrl" class="space-y-4">
                <!-- Audio Preview -->
                <RecordingPlayback :audio-url="audioUrl" :fallback-duration="formattedDuration" />

                <!-- Name Input -->
                <div class="space-y-2">
                    <Label for="recording-name">{{ t('shared.voice_recorder.recording_name_label') }}</Label>
                    <Input id="recording-name" v-model="recordingName" :placeholder="t('shared.voice_recorder.recording_name_placeholder')" />
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <Button @click="handleDiscard" variant="outline" class="flex-1">
                        <Trash2 class="me-2 h-4 w-4" />
                        {{ t('shared.voice_recorder.discard') }}
                    </Button>
                    <Button @click="handleSave" class="flex-1">
                        <Upload class="me-2 h-4 w-4" />
                        {{ t('shared.voice_recorder.save_recording') }}
                    </Button>
                </div>
            </div>

            <!-- Tips -->
            <div v-if="!isRecording && !recordingData" class="text-muted-foreground text-sm">
                <p class="mb-1 font-medium">{{ t('shared.voice_recorder.tips_heading') }}</p>
                <ul class="list-inside list-disc space-y-0.5">
                    <li>{{ t('shared.voice_recorder.tip_speak_clearly') }}</li>
                    <li>{{ t('shared.voice_recorder.tip_quiet_environment') }}</li>
                    <li>
                        {{ t('shared.voice_recorder.tip_max_duration', { minutes: maxDurationMinutes }, { plural: maxDurationMinutes }) }}
                    </li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
