import { ref, computed } from 'vue';
import axios from 'axios';

export function useVoiceRecorder() {
    const isRecording = ref(false);
    const isTranscribing = ref(false);
    const error = ref<string | null>(null);
    const transcript = ref('');
    
    let mediaRecorder: MediaRecorder | null = null;
    let audioChunks: Blob[] = [];
    let stream: MediaStream | null = null;

    const isSupported = computed(() => {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    });

    const startRecording = async () => {
        error.value = null;
        audioChunks = [];

        try {
            stream = await navigator.mediaDevices.getUserMedia({ 
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: 16000,
                }
            });

            mediaRecorder = new MediaRecorder(stream, {
                mimeType: MediaRecorder.isTypeSupported('audio/webm') 
                    ? 'audio/webm' 
                    : 'audio/mp4'
            });

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunks.push(event.data);
                }
            };

            mediaRecorder.start(100); // Collect data every 100ms
            isRecording.value = true;
        } catch (err: any) {
            error.value = err.message || 'Failed to access microphone';
            console.error('Error starting recording:', err);
        }
    };

    const stopRecording = async (): Promise<string> => {
        return new Promise((resolve, reject) => {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                resolve('');
                return;
            }

            mediaRecorder.onstop = async () => {
                isRecording.value = false;
                
                // Stop all tracks
                if (stream) {
                    stream.getTracks().forEach(track => track.stop());
                    stream = null;
                }

                if (audioChunks.length === 0) {
                    resolve('');
                    return;
                }

                const audioBlob = new Blob(audioChunks, { 
                    type: mediaRecorder?.mimeType || 'audio/webm' 
                });

                // Transcribe the audio
                try {
                    isTranscribing.value = true;
                    const text = await transcribeAudio(audioBlob);
                    transcript.value = text;
                    resolve(text);
                } catch (err: any) {
                    error.value = err.message || 'Transcription failed';
                    reject(err);
                } finally {
                    isTranscribing.value = false;
                }
            };

            mediaRecorder.stop();
        });
    };

    const cancelRecording = () => {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
        
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }
        
        isRecording.value = false;
        audioChunks = [];
    };

    const transcribeAudio = async (audioBlob: Blob): Promise<string> => {
        const formData = new FormData();
        
        // Determine file extension based on mime type
        const extension = audioBlob.type.includes('webm') ? 'webm' : 'mp4';
        formData.append('audio', audioBlob, `recording.${extension}`);

        const response = await axios.post(route('chat.transcribe'), formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        return response.data.text || '';
    };

    return {
        isRecording,
        isTranscribing,
        isSupported,
        error,
        transcript,
        startRecording,
        stopRecording,
        cancelRecording,
    };
}

