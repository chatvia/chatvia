import { computed, onUnmounted, ref } from 'vue';

export interface RecordingData {
    blob: Blob;
    duration: number;
    mimeType: string;
}

export function useSourceVoiceRecorder() {
    const isRecording = ref(false);
    const isPaused = ref(false);
    const error = ref<string | null>(null);
    const recordingDuration = ref(0);
    const audioLevel = ref(0);

    let mediaRecorder: MediaRecorder | null = null;
    let audioChunks: Blob[] = [];
    let stream: MediaStream | null = null;
    let audioContext: AudioContext | null = null;
    let analyser: AnalyserNode | null = null;
    let animationFrameId: number | null = null;
    let durationInterval: number | null = null;
    let startTime: number = 0;
    let pausedDuration: number = 0;

    const isSupported = computed(() => {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    });

    const formattedDuration = computed(() => {
        const totalSeconds = Math.floor(recordingDuration.value / 1000);
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    });

    const updateAudioLevel = () => {
        if (!analyser) return;

        const dataArray = new Uint8Array(analyser.frequencyBinCount);
        analyser.getByteFrequencyData(dataArray);

        // Calculate average level
        const average = dataArray.reduce((sum, value) => sum + value, 0) / dataArray.length;
        audioLevel.value = average / 255; // Normalize to 0-1

        if (isRecording.value && !isPaused.value) {
            animationFrameId = requestAnimationFrame(updateAudioLevel);
        }
    };

    const startRecording = async () => {
        error.value = null;
        audioChunks = [];
        recordingDuration.value = 0;
        pausedDuration = 0;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 44100,
                },
            });

            // Set up audio analyzer for visualization
            audioContext = new AudioContext();
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 256;
            const source = audioContext.createMediaStreamSource(stream);
            source.connect(analyser);

            // Determine best available mime type
            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : MediaRecorder.isTypeSupported('audio/webm')
                  ? 'audio/webm'
                  : 'audio/mp4';

            mediaRecorder = new MediaRecorder(stream, { mimeType });

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };

            mediaRecorder.start(100);
            isRecording.value = true;
            isPaused.value = false;
            startTime = Date.now();

            // Start duration timer
            durationInterval = window.setInterval(() => {
                if (!isPaused.value) {
                    recordingDuration.value = Date.now() - startTime + pausedDuration;
                }
            }, 100);

            // Start audio level monitoring
            updateAudioLevel();
        } catch (err: any) {
            if (err.name === 'NotAllowedError') {
                error.value = 'Microphone access denied. Please allow microphone access to record.';
            } else if (err.name === 'NotFoundError') {
                error.value = 'No microphone found. Please connect a microphone and try again.';
            } else {
                error.value = err.message || 'Failed to access microphone';
            }
            console.error('Error starting recording:', err);
        }
    };

    const pauseRecording = () => {
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.pause();
            isPaused.value = true;
            pausedDuration = recordingDuration.value;
            if (animationFrameId) {
                cancelAnimationFrame(animationFrameId);
            }
        }
    };

    const resumeRecording = () => {
        if (mediaRecorder && mediaRecorder.state === 'paused') {
            mediaRecorder.resume();
            isPaused.value = false;
            startTime = Date.now();
            updateAudioLevel();
        }
    };

    const stopRecording = async (): Promise<RecordingData | null> => {
        return new Promise((resolve) => {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                resolve(null);
                return;
            }

            mediaRecorder.onstop = () => {
                const duration = recordingDuration.value;

                // Clean up
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
                if (stream) {
                    stream.getTracks().forEach((track) => track.stop());
                    stream = null;
                }

                isRecording.value = false;
                isPaused.value = false;
                audioLevel.value = 0;

                if (audioChunks.length === 0) {
                    resolve(null);
                    return;
                }

                const mimeType = mediaRecorder?.mimeType || 'audio/webm';
                const audioBlob = new Blob(audioChunks, { type: mimeType });

                resolve({
                    blob: audioBlob,
                    duration,
                    mimeType,
                });
            };

            mediaRecorder.stop();
        });
    };

    const cancelRecording = () => {
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
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }

        isRecording.value = false;
        isPaused.value = false;
        recordingDuration.value = 0;
        audioLevel.value = 0;
        audioChunks = [];
    };

    // Cleanup on unmount
    onUnmounted(() => {
        cancelRecording();
    });

    return {
        isRecording,
        isPaused,
        isSupported,
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
