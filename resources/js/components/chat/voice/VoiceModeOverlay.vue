<script setup lang="ts">
/**
 * The hosted chat's voice mode: covers the chat panel while a live voice call
 * runs. The orb follows the visitor's microphone while they talk and the
 * agent's voice while it answers; the status line and the captions say what
 * is happening; Mute and End sit at the bottom (Esc ends too). The call
 * starts when the overlay opens and is hung up when it closes.
 *
 * Finished turns are passed up (`turn`) so the page can add them to the
 * thread; the server saves them on its own.
 */
import { endVoiceCall, startVoiceCall, type VoiceCallStarted } from '@/api/chat';
import VoiceOrb from '@/components/chat/voice/VoiceOrb.vue';
import { Button } from '@/components/ui/button';
import { useRealtimeVoice, type VoiceTurnEvent } from '@/composables/chat/useRealtimeVoice';
import { useAudioLevel } from '@/composables/useAudioLevel';
import { Mic, MicOff, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    /** The conversation to talk in; none opens a new one. */
    conversationId?: string;
}>();

const emit = defineEmits<{
    turn: [event: VoiceTurnEvent];
    started: [call: VoiceCallStarted];
    close: [];
}>();

const { t } = useI18n();

/** How long "Call ended" shows before the overlay closes. */
const CLOSE_DELAY_MS = 1200;

const call = useRealtimeVoice({
    open: (sdp) => startVoiceCall(sdp, props.conversationId),
    hangUp: endVoiceCall,
    onTurn: (event) => emit('turn', event),
    onStarted: (started) => emit('started', started),
});
const { state, error, muted, userCaption, agentCaption, startedAt } = call;

const microphone = useAudioLevel();
const agentVoice = useAudioLevel();

watch(call.localStream, (stream) => (stream ? microphone.attach(stream) : microphone.detach()));
watch(call.remoteStream, (stream) => (stream ? agentVoice.attach(stream) : agentVoice.detach()));

const orbMode = computed(() => {
    if (state.value === 'speaking') return 'speaking';
    if (state.value === 'thinking' || state.value === 'connecting' || state.value === 'waiting') return 'thinking';
    if (state.value === 'listening') return 'listening';

    return 'idle';
});

const orbLevel = computed(() => {
    if (state.value === 'speaking') return agentVoice.level.value;
    if (state.value === 'listening' && !muted.value) return microphone.level.value;

    return 0;
});

const status = computed(() => {
    switch (state.value) {
        case 'connecting':
        case 'waiting':
            return t('voice_call.connecting');
        case 'listening':
            return muted.value ? t('voice_call.muted') : t('voice_call.listening');
        case 'thinking':
            return t('voice_call.thinking');
        case 'speaking':
            return t('voice_call.speaking');
        case 'ended':
            return t('voice_call.ended');
        default:
            return '';
    }
});

/** The agent's words while it answers, else the visitor's. */
const caption = computed(() =>
    state.value === 'speaking' || (state.value === 'thinking' && agentCaption.value) ? agentCaption.value : userCaption.value,
);
const isLive = computed(() => ['connecting', 'waiting', 'listening', 'thinking', 'speaking'].includes(state.value));

// Elapsed time, from the moment the agent starts listening.
const now = ref(Date.now());
const clock = window.setInterval(() => (now.value = Date.now()), 1000);
const elapsed = computed(() => {
    if (startedAt.value === null) return null;

    const seconds = Math.max(0, Math.floor((now.value - startedAt.value) / 1000));

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
});

watch(state, (value) => {
    if (value === 'ended') window.setTimeout(() => emit('close'), CLOSE_DELAY_MS);
});

async function end(): Promise<void> {
    if (isLive.value) {
        await call.hangUp();
        return;
    }

    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') void end();
}

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
    void call.start();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    clearInterval(clock);
});
</script>

<template>
    <div
        class="bg-background/95 animate-in fade-in absolute inset-0 z-30 flex flex-col items-center justify-between px-6 pt-10 pb-8 backdrop-blur-sm duration-200"
        role="dialog"
        aria-modal="true"
        :aria-label="t('voice_call.title')"
    >
        <div class="text-muted-foreground h-5 text-sm tabular-nums">{{ elapsed }}</div>

        <div class="flex w-full max-w-md flex-1 flex-col items-center justify-center gap-6">
            <div class="size-56 sm:size-64">
                <VoiceOrb :level="orbLevel" :mode="orbMode" :dimmed="muted || state === 'error' || state === 'ended'" />
            </div>

            <p class="text-foreground text-base font-medium" role="status" aria-live="polite">{{ status }}</p>

            <p v-if="error" class="text-destructive max-w-sm text-center text-sm" role="alert">{{ error }}</p>
            <p v-else class="text-muted-foreground line-clamp-3 min-h-[3.75rem] max-w-sm text-center text-sm leading-5">{{ caption }}</p>
        </div>

        <div class="flex items-center gap-6">
            <Button
                v-if="isLive"
                type="button"
                variant="outline"
                size="icon"
                class="size-14 rounded-full"
                :class="muted ? 'bg-muted' : ''"
                :disabled="state === 'connecting' || state === 'waiting'"
                :aria-label="muted ? t('voice_call.unmute') : t('voice_call.mute')"
                :aria-pressed="muted"
                @click="call.toggleMute"
            >
                <MicOff v-if="muted" class="size-6" />
                <Mic v-else class="size-6" />
            </Button>
            <Button
                type="button"
                size="icon"
                class="size-14 rounded-full"
                :variant="isLive ? 'destructive' : 'secondary'"
                :aria-label="isLive ? t('voice_call.end') : t('voice_call.close')"
                @click="end"
            >
                <X class="size-6" />
            </Button>
        </div>
    </div>
</template>
