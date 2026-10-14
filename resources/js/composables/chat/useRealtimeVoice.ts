/**
 * One live voice call with the agent. The browser's microphone and the
 * agent's voice travel over WebRTC straight to the speech model; the server
 * only opens the call (it answers our offer) and joins it to steer it. The
 * data channel ("oai-events") carries the call's events, which drive the
 * state, the captions and the transcript.
 *
 * The microphone stays silent until the server has joined (it turns on
 * listening, seen here as `session.updated`), so nothing is answered before
 * the server can check and save it. If it does not join in time, the call
 * ends as "busy".
 */
import type { VoiceCallStarted } from '@/api/chat';
import { errorMessage } from '@/api/http';
import { onBeforeUnmount, ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';

export type VoiceCallState = 'idle' | 'connecting' | 'waiting' | 'listening' | 'thinking' | 'speaking' | 'ended' | 'error';

/** A line of the call for the chat thread: the visitor's turn starts, their words arrive, or the agent replied. */
export type VoiceTurnEvent = { kind: 'user-started'; id: string } | { kind: 'user'; id: string; text: string } | { kind: 'agent'; text: string };

interface Options {
    /** Open the call on the server for this offer. */
    open: (sdp: string) => Promise<VoiceCallStarted>;
    /** Hang the call up on the server. */
    hangUp: (callId: string) => Promise<void>;
    onTurn?: (event: VoiceTurnEvent) => void;
    onStarted?: (call: VoiceCallStarted) => void;
}

interface ServerEvent {
    type?: string;
    item_id?: string;
    delta?: string;
    transcript?: string;
    session?: { audio?: { input?: { turn_detection?: unknown } } };
    response?: {
        metadata?: Record<string, string> | null;
        output?: { type?: string; content?: { transcript?: string; text?: string }[] }[];
    };
}

/** Seconds past the call's limit after which the browser hangs up on its own. */
const LIMIT_GRACE_SECONDS = 30;

export function isVoiceCallSupported(): boolean {
    return typeof window !== 'undefined' && 'RTCPeerConnection' in window && !!navigator.mediaDevices?.getUserMedia;
}

export function useRealtimeVoice(options: Options) {
    const { t } = useI18n();

    const state = ref<VoiceCallState>('idle');
    const error = ref<string | null>(null);
    const muted = ref(false);
    const userCaption = ref('');
    const agentCaption = ref('');
    const startedAt = ref<number | null>(null);
    const localStream = shallowRef<MediaStream | null>(null);
    const remoteStream = shallowRef<MediaStream | null>(null);

    let peer: RTCPeerConnection | null = null;
    let channel: RTCDataChannel | null = null;
    let player: HTMLAudioElement | null = null;
    let callId: string | null = null;
    let readyTimer: number | null = null;
    let limitTimer: number | null = null;

    const microphone = (): MediaStreamTrack | undefined => localStream.value?.getAudioTracks()[0];
    const isActive = (): boolean => !['idle', 'ended', 'error'].includes(state.value);

    async function start(): Promise<void> {
        if (isActive()) return;

        error.value = null;
        state.value = 'connecting';

        if (!isVoiceCallSupported()) {
            fail(t('voice_call.errors.not_supported'));
            return;
        }

        try {
            localStream.value = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            });
        } catch (err) {
            const name = (err as { name?: string }).name;
            fail(name === 'NotFoundError' ? t('voice_call.errors.microphone_not_found') : t('voice_call.errors.microphone_denied'));
            return;
        }

        // Silent until the server has joined the call.
        const track = microphone();
        if (track) track.enabled = false;

        try {
            peer = new RTCPeerConnection();
            player = new Audio();
            player.autoplay = true;

            peer.ontrack = (event) => {
                remoteStream.value = event.streams[0] ?? null;
                if (player) player.srcObject = remoteStream.value;
            };
            peer.onconnectionstatechange = () => {
                if (peer && ['failed', 'closed'].includes(peer.connectionState) && isActive()) {
                    close();
                }
            };

            if (track && localStream.value) peer.addTrack(track, localStream.value);

            channel = peer.createDataChannel('oai-events');
            channel.onmessage = (message) => onServerEvent(message.data);
            channel.onclose = () => {
                if (isActive()) close();
            };

            const offer = await peer.createOffer();
            await peer.setLocalDescription(offer);

            const call = await options.open(offer.sdp ?? '');
            // Ended while the server answered.
            if (!peer) return;

            callId = call.call_id;
            options.onStarted?.(call);
            await peer.setRemoteDescription({ type: 'answer', sdp: call.sdp });

            state.value = 'waiting';
            readyTimer = window.setTimeout(() => {
                if (state.value === 'waiting') {
                    void hangUp();
                    fail(t('voice_call.errors.busy'));
                }
            }, call.join_timeout * 1000);
            limitTimer = window.setTimeout(() => void hangUp(), (call.max_seconds + LIMIT_GRACE_SECONDS) * 1000);
        } catch (err) {
            close();
            fail(errorMessage(err, t('voice_call.errors.failed')));
        }
    }

    function onServerEvent(raw: unknown): void {
        let event: ServerEvent;

        try {
            event = JSON.parse(String(raw)) as ServerEvent;
        } catch {
            return;
        }

        switch (event.type) {
            case 'session.updated':
                if (event.session?.audio?.input?.turn_detection) ready();
                break;
            case 'input_audio_buffer.speech_started':
                ready();
                state.value = 'listening';
                userCaption.value = '';
                break;
            case 'input_audio_buffer.speech_stopped':
                state.value = 'thinking';
                break;
            case 'input_audio_buffer.committed':
                if (event.item_id) options.onTurn?.({ kind: 'user-started', id: event.item_id });
                break;
            case 'conversation.item.input_audio_transcription.delta':
                userCaption.value += event.delta ?? '';
                break;
            case 'conversation.item.input_audio_transcription.completed':
                userCaption.value = event.transcript ?? userCaption.value;
                if (event.item_id) options.onTurn?.({ kind: 'user', id: event.item_id, text: (event.transcript ?? '').trim() });
                break;
            case 'response.created':
                agentCaption.value = '';
                if (state.value !== 'speaking') state.value = 'thinking';
                break;
            case 'response.output_audio_transcript.delta':
                state.value = 'speaking';
                agentCaption.value += event.delta ?? '';
                break;
            case 'output_audio_buffer.started':
                state.value = 'speaking';
                break;
            case 'output_audio_buffer.stopped':
                if (isActive() && state.value !== 'waiting') state.value = 'listening';
                break;
            case 'response.done':
                onResponseDone(event.response);
                break;
        }
    }

    /** A finished reply goes to the thread; notices ("let me check", goodbye, "too busy") do not. */
    function onResponseDone(response: ServerEvent['response']): void {
        if (!response || response.metadata?.notice) return;

        const text = (response.output ?? [])
            .filter((item) => item.type === 'message')
            .flatMap((item) => item.content ?? [])
            .map((part) => part.transcript ?? part.text ?? '')
            .join(' ')
            .trim();

        if (text) options.onTurn?.({ kind: 'agent', text });
    }

    /** The server joined and turned listening on: open the microphone. */
    function ready(): void {
        if (state.value !== 'waiting') return;

        if (readyTimer !== null) {
            clearTimeout(readyTimer);
            readyTimer = null;
        }

        const track = microphone();
        if (track) track.enabled = !muted.value;

        startedAt.value = Date.now();
        state.value = 'listening';
    }

    function toggleMute(): void {
        muted.value = !muted.value;

        const track = microphone();
        if (track && state.value !== 'waiting') track.enabled = !muted.value;
    }

    /** End the call: the server hangs up the provider's side too. */
    async function hangUp(): Promise<void> {
        const id = callId;
        close();

        if (id) {
            try {
                await options.hangUp(id);
            } catch {
                // The server ends the call on its own at the latest by its time limit.
            }
        }
    }

    function close(): void {
        for (const timer of [readyTimer, limitTimer]) {
            if (timer !== null) clearTimeout(timer);
        }
        readyTimer = null;
        limitTimer = null;

        if (channel) {
            channel.onclose = null;
            channel.onmessage = null;
            channel.close();
            channel = null;
        }

        if (peer) {
            peer.onconnectionstatechange = null;
            peer.ontrack = null;
            peer.close();
            peer = null;
        }

        localStream.value?.getTracks().forEach((track) => track.stop());
        localStream.value = null;
        remoteStream.value = null;

        if (player) {
            player.srcObject = null;
            player = null;
        }

        callId = null;
        if (state.value !== 'error') state.value = 'ended';
    }

    function fail(message: string): void {
        error.value = message;
        state.value = 'error';
        close();
    }

    onBeforeUnmount(() => void hangUp());

    return { state, error, muted, userCaption, agentCaption, startedAt, localStream, remoteStream, start, hangUp, toggleMute };
}
