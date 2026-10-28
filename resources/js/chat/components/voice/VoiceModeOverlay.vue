<script setup lang="ts">
/**
 * The hosted chat's voice mode: covers the chat panel while a live voice call
 * runs. The orb follows the visitor's microphone while they talk and the
 * agent's voice while it answers, and the status line says what is
 * happening. Below it, the call's live transcript: each turn on its own line
 * (the visitor's at the end, the agent's at the start, as in the thread) and
 * a chip for every tool the agent calls, with how long it took and how it
 * went. The orb starts large and makes room once the first line arrives.
 * Mute and End sit at the bottom (Esc ends too). The call starts when the
 * overlay opens and is hung up when it closes.
 *
 * Finished turns are passed up (`turn`) so the page can add them to the
 * thread; the server saves them on its own.
 */
import { endVoiceCall, startVoiceCall, type VoiceCallStarted } from '@/chat/api/chat';
import VoiceOrb from '@/chat/components/voice/VoiceOrb.vue';
import { Button } from '@/shared/ui/button';
import { useRealtimeVoice, type VoiceLine, type VoiceTurnEvent } from '@/chat/composables/useRealtimeVoice';
import { useAudioLevel } from '@/shared/composables/useAudioLevel';
import { Ban, BookOpen, Check, CircleAlert, Hand, LoaderCircle, Mic, MicOff, Plug, Webhook, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type ToolLine = Extract<VoiceLine, { kind: 'tool' }>;

const props = defineProps<{
    /** The conversation to talk in; none opens a new one. */
    conversationId?: string;
}>();

const emit = defineEmits<{
    turn: [event: VoiceTurnEvent];
    started: [call: VoiceCallStarted];
    close: [];
}>();

const { t, locale } = useI18n();

/** How long "Call ended" shows before the overlay closes. */
const CLOSE_DELAY_MS = 1200;

/** How close to the bottom (px) the transcript still counts as following the newest line. */
const FOLLOW_SLACK_PX = 48;

const call = useRealtimeVoice({
    open: (sdp) => startVoiceCall(sdp, props.conversationId),
    hangUp: endVoiceCall,
    onTurn: (event) => emit('turn', event),
    onStarted: (started) => emit('started', started),
});
const { state, error, muted, lines, startedAt } = call;

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

const isLive = computed(() => ['connecting', 'waiting', 'listening', 'thinking', 'speaking'].includes(state.value));
const hasLines = computed(() => lines.value.length > 0);

// The transcript keeps the newest line in view while the visitor follows
// along; once they scroll up to reread, it stays where they are.
const transcriptBox = ref<HTMLElement | null>(null);
const following = ref(true);
const scrolled = ref(false);
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function onTranscriptScroll(): void {
    const box = transcriptBox.value;
    if (!box) return;

    following.value = box.scrollHeight - box.scrollTop - box.clientHeight < FOLLOW_SLACK_PX;
    scrolled.value = box.scrollTop > 0;
}

watch(
    lines,
    () =>
        nextTick(() => {
            const box = transcriptBox.value;
            if (!box || !following.value) return;

            box.scrollTo({ top: box.scrollHeight, behavior: reducedMotion ? 'auto' : 'smooth' });
        }),
    { deep: true },
);

function toolLabel(line: ToolLine): string {
    return line.tool.kind === 'knowledge' ? t('voice_call.tools.knowledge') : line.tool.label;
}

function toolAction(line: ToolLine): string | null {
    return line.tool.kind === 'knowledge' ? null : line.tool.action;
}

function toolIcon(line: ToolLine): string | null {
    return line.tool.kind === 'knowledge' ? null : line.tool.icon_url;
}

/** "204ms", or "1.4s" once it took a second or more, in the chat's language. */
function duration(ms: number): string {
    const seconds = ms >= 1000;

    return new Intl.NumberFormat(locale.value, {
        style: 'unit',
        unit: seconds ? 'second' : 'millisecond',
        unitDisplay: 'narrow',
        maximumFractionDigits: 1,
    }).format(seconds ? ms / 1000 : ms);
}

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
        class="bg-background/95 animate-in fade-in absolute inset-0 z-30 flex flex-col items-center px-4 pt-6 pb-8 backdrop-blur-sm duration-200 sm:px-6"
        role="dialog"
        aria-modal="true"
        :aria-label="t('voice_call.title')"
    >
        <div class="text-muted-foreground h-5 shrink-0 text-sm tabular-nums">{{ elapsed }}</div>

        <div class="flex w-full shrink-0 flex-col items-center gap-4" :class="hasLines ? 'pt-2' : 'flex-1 justify-center'">
            <div
                class="transition-[width,height] duration-500 ease-out motion-reduce:transition-none"
                :class="hasLines ? 'size-28 sm:size-36' : 'size-56 sm:size-64'"
            >
                <VoiceOrb :level="orbLevel" :mode="orbMode" :dimmed="muted || state === 'error' || state === 'ended'" />
            </div>

            <p class="text-foreground text-base font-medium" role="status" aria-live="polite">{{ status }}</p>

            <p v-if="error" class="text-destructive max-w-sm text-center text-sm" role="alert">{{ error }}</p>
        </div>

        <div
            v-if="hasLines"
            ref="transcriptBox"
            class="mt-4 min-h-0 w-full max-w-xl flex-1 overflow-y-auto"
            :class="{ '[mask-image:linear-gradient(to_bottom,transparent,black_2.5rem)]': scrolled }"
            @scroll.passive="onTranscriptScroll"
        >
            <ol class="flex min-h-full flex-col justify-end gap-3 px-1 pt-6 pb-1" :aria-label="t('voice_call.transcript')">
                <template v-for="line in lines" :key="`${line.kind}:${line.id}`">
                    <li
                        v-if="line.kind === 'user'"
                        dir="auto"
                        class="text-primary max-w-[85%] self-end text-end text-[15px] leading-relaxed font-medium break-words"
                    >
                        <span v-if="line.pending && !line.text" class="text-muted-foreground animate-pulse">…</span>
                        <template v-else>{{ line.text }}</template>
                    </li>

                    <li
                        v-else-if="line.kind === 'agent'"
                        dir="auto"
                        class="text-foreground max-w-[90%] self-start text-[15px] leading-relaxed break-words"
                    >
                        {{ line.text }}
                    </li>

                    <li v-else class="max-w-full self-start">
                        <div
                            class="bg-muted/50 border-border inline-flex max-w-full items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs"
                            :class="{ 'opacity-60': line.status === 'cancelled' }"
                        >
                            <img v-if="toolIcon(line)" :src="toolIcon(line) ?? undefined" alt="" class="size-4 shrink-0 rounded-sm object-contain" />
                            <BookOpen v-else-if="line.tool.kind === 'knowledge'" class="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />
                            <Webhook v-else-if="line.tool.kind === 'function'" class="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />
                            <Plug v-else class="text-muted-foreground size-3.5 shrink-0" aria-hidden="true" />

                            <!-- On a narrow screen the tool's own name gives way before the service's. -->
                            <span class="text-foreground max-w-[60%] shrink-0 truncate font-medium">{{ toolLabel(line) }}</span>
                            <span v-if="toolAction(line)" class="text-muted-foreground min-w-0 truncate font-mono">{{ toolAction(line) }}</span>

                            <template v-if="line.status === 'running'">
                                <LoaderCircle class="text-muted-foreground size-3.5 shrink-0 animate-spin" aria-hidden="true" />
                                <span class="sr-only">{{ t('voice_call.tools.running') }}</span>
                            </template>
                            <template v-else-if="line.status === 'done'">
                                <span v-if="line.ms !== null" class="text-muted-foreground shrink-0 font-mono tabular-nums">{{
                                    duration(line.ms)
                                }}</span>
                                <Check class="size-3.5 shrink-0 text-emerald-500" aria-hidden="true" />
                                <span class="sr-only">{{ t('voice_call.tools.done') }}</span>
                            </template>
                            <span v-else-if="line.status === 'held'" class="flex shrink-0 items-center gap-1 text-amber-600 dark:text-amber-400">
                                <Hand class="size-3.5" aria-hidden="true" />
                                {{ t('voice_call.tools.held') }}
                            </span>
                            <span v-else-if="line.status === 'failed'" class="text-destructive flex shrink-0 items-center gap-1">
                                <CircleAlert class="size-3.5" aria-hidden="true" />
                                {{ t('voice_call.tools.failed') }}
                            </span>
                            <span v-else class="text-muted-foreground flex shrink-0 items-center gap-1">
                                <Ban class="size-3.5" aria-hidden="true" />
                                {{ t('voice_call.tools.cancelled') }}
                            </span>
                        </div>
                    </li>
                </template>
            </ol>
        </div>

        <div class="mt-6 flex shrink-0 items-center gap-6">
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
