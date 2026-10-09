/**
 * Microphone capture shared by the chat composer's voice input
 * (`useVoiceRecorder`) and the knowledge-base voice-note recorder
 * (`useSourceVoiceRecorder`). Owns the MediaStream + MediaRecorder lifecycle
 * and hands back the recorded audio as one Blob; callers add transcription,
 * timers or level meters on top.
 */
import { computed } from 'vue';

export interface AudioCaptureOptions {
    /** Requested sample rate for the microphone track. */
    sampleRate: number;
    /** Container formats to try, best first. The last entry is used when none is supported. */
    mimeTypes: readonly string[];
}

/** How often MediaRecorder flushes a chunk while recording. */
const CHUNK_INTERVAL_MS = 100;

/** Used when the browser reports no MIME type for the finished recording. */
const FALLBACK_MIME_TYPE = 'audio/webm';

export function useAudioCapture(options: AudioCaptureOptions) {
    let mediaRecorder: MediaRecorder | null = null;
    let stream: MediaStream | null = null;
    let chunks: Blob[] = [];

    const isSupported = computed(() => !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia));

    function pickMimeType(): string {
        const supported = options.mimeTypes.find((type) => MediaRecorder.isTypeSupported(type));

        return supported ?? options.mimeTypes[options.mimeTypes.length - 1];
    }

    function releaseStream() {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    /**
     * Asks for the microphone and starts recording. `onStream` runs before the
     * recorder starts, so callers can attach an analyser to the same stream.
     * Rejects when access is denied or no microphone exists.
     */
    async function start(onStream?: (stream: MediaStream) => void): Promise<void> {
        chunks = [];

        stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                sampleRate: options.sampleRate,
            },
        });

        onStream?.(stream);

        mediaRecorder = new MediaRecorder(stream, { mimeType: pickMimeType() });
        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        };
        mediaRecorder.start(CHUNK_INTERVAL_MS);
    }

    /** True while a recording is running or paused. */
    function isActive(): boolean {
        return mediaRecorder !== null && mediaRecorder.state !== 'inactive';
    }

    function pause(): boolean {
        if (mediaRecorder?.state !== 'recording') return false;
        mediaRecorder.pause();

        return true;
    }

    function resume(): boolean {
        if (mediaRecorder?.state !== 'paused') return false;
        mediaRecorder.resume();

        return true;
    }

    /**
     * Stops recording and releases the microphone. Resolves (from the
     * recorder's `stop` event) with the recorded audio, or null when nothing
     * was captured.
     */
    function stop(): Promise<Blob | null> {
        return new Promise((resolve) => {
            const recorder = mediaRecorder;
            if (!recorder || recorder.state === 'inactive') {
                resolve(null);
                return;
            }

            recorder.onstop = () => {
                releaseStream();

                if (chunks.length === 0) {
                    resolve(null);
                    return;
                }

                resolve(new Blob(chunks, { type: recorder.mimeType || FALLBACK_MIME_TYPE }));
            };

            recorder.stop();
        });
    }

    /** Stops without keeping the audio. */
    function cancel() {
        if (isActive()) {
            mediaRecorder?.stop();
        }
        releaseStream();
        chunks = [];
    }

    return {
        isSupported,
        start,
        isActive,
        pause,
        resume,
        stop,
        cancel,
    };
}
