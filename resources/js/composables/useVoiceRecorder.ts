/**
 * Voice input for the chat composer: records the microphone (via
 * `useAudioCapture`) and turns the recording into text with the hosted chat's
 * transcription endpoint. `stopRecording()` resolves with the transcript.
 */
import { transcribeAudio } from '@/api/chat';
import { useAudioCapture } from '@/composables/useAudioCapture';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

export function useVoiceRecorder() {
    const { t } = useI18n();
    const capture = useAudioCapture({ sampleRate: 16000, mimeTypes: ['audio/webm', 'audio/mp4'] });

    const isRecording = ref(false);
    const isTranscribing = ref(false);
    const error = ref<string | null>(null);
    const transcript = ref('');

    const startRecording = async () => {
        error.value = null;

        try {
            await capture.start();
            isRecording.value = true;
        } catch (err) {
            error.value = (err as Error).message || t('shared.voice_recorder.microphone_failed');
        }
    };

    const stopRecording = async (): Promise<string> => {
        if (!capture.isActive()) return '';

        const audio = await capture.stop();
        isRecording.value = false;
        if (!audio) return '';

        isTranscribing.value = true;
        try {
            const extension = audio.type.includes('webm') ? 'webm' : 'mp4';
            const text = await transcribeAudio(audio, `recording.${extension}`);
            transcript.value = text;

            return text;
        } catch (err) {
            error.value = (err as Error).message || t('shared.voice_recorder.transcription_failed');
            throw err;
        } finally {
            isTranscribing.value = false;
        }
    };

    const cancelRecording = () => {
        capture.cancel();
        isRecording.value = false;
    };

    return {
        isRecording,
        isTranscribing,
        isSupported: capture.isSupported,
        error,
        transcript,
        startRecording,
        stopRecording,
        cancelRecording,
    };
}
