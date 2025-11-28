<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { useSourceVoiceRecorder, type RecordingData } from '@/composables/useSourceVoiceRecorder';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Mic, 
    Square, 
    Pause, 
    Play, 
    Trash2, 
    Upload, 
    AlertCircle,
    CheckCircle
} from 'lucide-vue-next';

interface Props {
    title?: string;
    description?: string;
    maxDuration?: number; // in seconds
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Record Voice Note',
    description: 'Record audio directly from your microphone',
    maxDuration: 600, // 10 minutes default
    disabled: false,
});

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
    recordingDuration
} = useSourceVoiceRecorder();

// States
const recordingData = ref<RecordingData | null>(null);
const recordingName = ref('');
const audioUrl = ref<string | null>(null);
const isPlaying = ref(false);
const playbackProgress = ref(0);
const audioElement = ref<HTMLAudioElement | null>(null);
const progressBarRef = ref<HTMLDivElement | null>(null);

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

// Waveform bars (12 bars for visualization)
const waveformBars = computed(() => {
    const bars = [];
    const numBars = 12;
    for (let i = 0; i < numBars; i++) {
        const baseHeight = isRecording.value && !isPaused.value
            ? 0.2 + (audioLevel.value * 0.8 * Math.sin((Date.now() / 100) + i * 0.5))
            : 0.15;
        const height = Math.max(0.1, Math.min(1, baseHeight + (Math.random() * 0.1)));
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
        duration: recordingData.value.duration
    });
    
    recordingData.value = null;
    if (audioUrl.value) {
        URL.revokeObjectURL(audioUrl.value);
        audioUrl.value = null;
    }
    recordingName.value = '';
};

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
                {{ title }}
            </CardTitle>
            <CardDescription>
                {{ description }}
            </CardDescription>
        </CardHeader>
        
        <CardContent class="space-y-6">
            <!-- Not Supported Alert -->
            <Alert v-if="!isSupported" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertDescription>
                    Your browser doesn't support audio recording. Please use a modern browser like Chrome, Firefox, or Safari.
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
                <div class="relative h-24 rounded-lg border bg-muted/50 flex items-center justify-center">
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
                        class="absolute bottom-2 left-1/2 -translate-x-1/2 flex items-center gap-2 px-3 py-1 rounded-full bg-background border text-sm"
                    >
                        <div 
                            class="w-2 h-2 rounded-full"
                            :class="isPaused ? 'bg-amber-500' : 'bg-red-500 animate-pulse'"
                        />
                        <span class="font-mono tabular-nums">{{ formattedDuration }}</span>
                    </div>
                    
                </div>
                
                <!-- Progress Bar -->
                <div v-if="isRecording" class="space-y-1">
                    <div class="flex justify-between text-xs text-muted-foreground">
                        <span>Recording</span>
                        <span>{{ remainingTime }} remaining</span>
                    </div>
                    <div class="h-1 bg-muted rounded-full overflow-hidden">
                        <div 
                            class="h-full bg-primary transition-all duration-300"
                            :style="{ width: `${recordingProgress}%` }"
                        />
                    </div>
                </div>
                
                <!-- Recording Controls -->
                <div class="flex items-center justify-center gap-3">
                    <template v-if="!isRecording">
                        <Button
                            @click="handleStartRecording"
                            :disabled="disabled"
                        >
                            <Mic class="h-4 w-4 mr-2" />
                            Start Recording
                        </Button>
                    </template>
                    
                    <template v-else>
                        <Button
                            @click="handlePauseResume"
                            variant="outline"
                            size="icon"
                        >
                            <Play v-if="isPaused" class="h-4 w-4" />
                            <Pause v-else class="h-4 w-4" />
                        </Button>
                        
                        <Button @click="handleStopRecording">
                            <Square class="h-4 w-4 mr-2" />
                            Stop
                        </Button>
                        
                        <Button
                            @click="handleCancel"
                            variant="outline"
                            size="icon"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </template>
                </div>
            </div>
            
            <!-- Preview & Save Interface -->
            <div v-if="recordingData && audioUrl" class="space-y-4">
                <!-- Audio Preview -->
                <div class="rounded-lg border bg-muted/50 p-4">
                    <div class="flex items-center gap-4">
                        <Button
                            @click="togglePlayback"
                            variant="outline"
                            size="icon"
                        >
                            <Pause v-if="isPlaying" class="h-4 w-4" />
                            <Play v-else class="h-4 w-4" />
                        </Button>
                        
                        <div class="flex-1 space-y-1">
                            <div 
                                ref="progressBarRef"
                                class="h-2 bg-muted rounded-full overflow-hidden cursor-pointer relative group"
                                @click="handleSeek"
                            >
                                <div 
                                    class="h-full bg-primary transition-all duration-100"
                                    :style="{ width: `${playbackProgress}%` }"
                                />
                                <!-- Seek indicator -->
                                <div 
                                    class="absolute top-1/2 -translate-y-1/2 w-3 h-3 bg-primary rounded-full shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                                    :style="{ left: `calc(${playbackProgress}% - 6px)` }"
                                />
                            </div>
                            <div class="flex justify-between text-xs text-muted-foreground">
                                <span class="font-mono">
                                    {{ audioElement ? formatPlaybackTime(audioElement.currentTime) : '0:00' }}
                                </span>
                                <span class="font-mono">
                                    {{ audioElement ? formatPlaybackTime(audioElement.duration || 0) : formattedDuration }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <CheckCircle class="h-4 w-4 text-green-600" />
                            <span>Recorded</span>
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
                
                <!-- Name Input -->
                <div class="space-y-2">
                    <Label for="recording-name">Recording Name</Label>
                    <Input
                        id="recording-name"
                        v-model="recordingName"
                        placeholder="Enter a name for your recording"
                    />
                </div>
                
                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <Button
                        @click="handleDiscard"
                        variant="outline"
                        class="flex-1"
                    >
                        <Trash2 class="h-4 w-4 mr-2" />
                        Discard
                    </Button>
                    <Button
                        @click="handleSave"
                        class="flex-1"
                    >
                        <Upload class="h-4 w-4 mr-2" />
                        Save Recording
                    </Button>
                </div>
            </div>
            
            <!-- Tips -->
            <div 
                v-if="!isRecording && !recordingData"
                class="text-sm text-muted-foreground"
            >
                <p class="font-medium mb-1">Tips:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li>Speak clearly and at a steady pace</li>
                    <li>Use a quiet environment for best results</li>
                    <li>Maximum recording length: {{ Math.floor(maxDuration / 60) }} minutes</li>
                </ul>
            </div>
        </CardContent>
    </Card>
</template>
