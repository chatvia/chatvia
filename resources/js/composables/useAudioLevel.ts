/**
 * The live loudness of an audio stream, as a 0–1 value refreshed every
 * animation frame: the average of an analyser's frequency bins. Used by the
 * knowledge-base voice recorder's level meter and by the voice-call orb (the
 * visitor's microphone, then the agent's voice).
 *
 * `attach()` starts measuring a stream (replacing the one before), `pause()`
 * / `resume()` hold the meter, `detach()` stops and frees the audio context.
 */
import { onBeforeUnmount, ref } from 'vue';

/** Analyser resolution: 128 frequency bins, plenty for a loudness value. */
const FFT_SIZE = 256;

export function useAudioLevel() {
    const level = ref(0);

    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let source: MediaStreamAudioSourceNode | null = null;
    let frame: number | null = null;
    let bins: Uint8Array<ArrayBuffer> | null = null;

    const measure = () => {
        if (!analyser || !bins) return;

        analyser.getByteFrequencyData(bins);
        level.value = bins.reduce((sum, value) => sum + value, 0) / bins.length / 255;
        frame = requestAnimationFrame(measure);
    };

    const pause = () => {
        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
        }
    };

    const resume = () => {
        if (frame === null && analyser) measure();
    };

    const detach = () => {
        pause();
        source?.disconnect();
        source = null;
        analyser = null;
        bins = null;
        level.value = 0;

        if (audioContext) {
            void audioContext.close();
            audioContext = null;
        }
    };

    const attach = (stream: MediaStream) => {
        detach();

        audioContext = new AudioContext();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = FFT_SIZE;
        bins = new Uint8Array(analyser.frequencyBinCount);
        source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);
        // A context created before a user gesture starts suspended.
        void audioContext.resume();
        measure();
    };

    onBeforeUnmount(detach);

    return { level, attach, detach, pause, resume };
}
