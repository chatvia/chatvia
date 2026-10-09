/**
 * Voice-note recorder for knowledge-base sources (VoiceRecorder.vue). Adds a
 * running duration, pause/resume and a live input level on top of the shared
 * microphone capture (`useAudioCapture`); the finished note is returned as a
 * Blob for upload.
 */
import { useAudioCapture } from '@/composables/useAudioCapture';
import { computed, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

export interface RecordingData {
    blob: Blob;
    duration: number;
    mimeType: string;
}

/** How often the visible duration is refreshed while recording. */
const DURATION_TICK_MS = 100;

export function useSourceVoiceRecorder() {
    const { t } = useI18n();
    const capture = useAudioCapture({
        sampleRate: 44100,
        mimeTypes: ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'],
    });

    const isRecording = ref(false);
    const isPaused = ref(false);
    const error = ref<string | null>(null);
    const recordingDuration = ref(0);
    const audioLevel = ref(0);

    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let animationFrameId: number | null = null;
    let durationInterval: number | null = null;
    let startTime = 0;
    let pausedDuration = 0;

    const formattedDuration = computed(() => {
        const totalSeconds = Math.floor(recordingDuration.value / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;

        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });

    // Average loudness across the analyser's frequency bins, normalised to 0–1.
    const updateAudioLevel = () => {
        if (!analyser) return;

        const dataArray = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(dataArray);
        const average = dataArray.reduce((sum, value) => sum + value, 0) / dataArray.length;
        audioLevel.value = average / 255;

        if (isRecording.value && !isPaused.value) {
            animationFrameId = requestAnimationFrame(updateAudioLevel);
        }
    };

    const attachAnalyser = (stream: MediaStream) => {
        audioContext = new AudioContext();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 256;
        audioContext.createMediaStreamSource(stream).connect(analyser);
    };

    const stopMeters = () => {
        if (durationInterval) {
            clearInterval(durationInterval);
            durationInterval = null;
        }
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
            animationFrameId = null;
        }
        if (audioContext) {
            audioContext.close();
            audioContext = null;
        }
    };

    const describeMicrophoneError = (err: unknown): string => {
        const name = (err as { name?: string }).name;
        if (name === 'NotAllowedError') return t('shared.voice_recorder.microphone_denied');
        if (name === 'NotFoundError') return t('shared.voice_recorder.microphone_not_found');

        return (err as Error).message || t('shared.voice_recorder.microphone_failed');
    };

    const startRecording = async () => {
        error.value = null;
        recordingDuration.value = 0;
        pausedDuration = 0;

        try {
            await capture.start(attachAnalyser);
        } catch (err) {
            error.value = describeMicrophoneError(err);
            return;
        }

        isRecording.value = true;
        isPaused.value = false;
        startTime = Date.now();

        durationInterval = window.setInterval(() => {
            if (!isPaused.value) {
                recordingDuration.value = Date.now() - startTime + pausedDuration;
            }
        }, DURATION_TICK_MS);

        updateAudioLevel();
    };

    const pauseRecording = () => {
        if (!capture.pause()) return;

        isPaused.value = true;
        pausedDuration = recordingDuration.value;
        if (animationFrameId) {
            cancelAnimationFrame(animationFrameId);
        }
    };

    const resumeRecording = () => {
        if (!capture.resume()) return;

        isPaused.value = false;
        startTime = Date.now();
        updateAudioLevel();
    };

    const stopRecording = async (): Promise<RecordingData | null> => {
        if (!capture.isActive()) return null;

        const blob = await capture.stop();
        const duration = recordingDuration.value;

        stopMeters();
        isRecording.value = false;
        isPaused.value = false;
        audioLevel.value = 0;

        return blob ? { blob, duration, mimeType: blob.type } : null;
    };

    const cancelRecording = () => {
        stopMeters();
        capture.cancel();

        isRecording.value = false;
        isPaused.value = false;
        recordingDuration.value = 0;
        audioLevel.value = 0;
    };

    onUnmounted(() => {
        cancelRecording();
    });

    return {
        isRecording,
        isPaused,
        isSupported: capture.isSupported,
        error,
        recordingDuration,
        formattedDuration,
        audioLevel,
        startRecording,
        pauseRecording,
        resumeRecording,
        stopRecording,
        cancelRecording,
    };
}
