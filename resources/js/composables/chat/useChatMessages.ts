/**
 * The hosted chat transcript: the ordered messages plus the knowledge sources
 * and artifacts (charts, forms, generated images and audio) of each assistant
 * reply, keyed by the reply's UUID. Owns loading and the local edits that
 * mirror what the server just did (new turn stubs, deleted pairs, removed
 * attachments, a generated file that is now made).
 */
import { fetchConversationMessages, fetchConversationSources } from '@/api/chat';
import type { ChatAttachment, Message, MessageSource } from '@/types/chat';
import { GENERATED_MEDIA_KINDS, type GeneratedMedia, type GeneratedMediaArtifactData } from '@/types/generatedMedia';
import type { ToolArtifact } from '@/types/stream';
import { computed, ref } from 'vue';

export type MessageId = string | number;

/** Where a turn's user prompt and assistant reply sit in the transcript. */
export interface TurnSlots {
    userIndex: number;
    assistantIndex: number;
}

export function useChatMessages(conversationId: string) {
    const messages = ref<Message[]>([]);
    const sources = ref(new Map<MessageId, MessageSource[]>());
    const artifacts = ref(new Map<MessageId, ToolArtifact[]>());

    async function load(): Promise<void> {
        messages.value = await fetchConversationMessages(conversationId);
        restoreArtifacts();
    }

    // Charts are persisted on each message. Without this, a reload shows the
    // model's "Here's a bar chart of …" caption with no chart underneath.
    function restoreArtifacts() {
        for (const message of messages.value) {
            const stored = message.metadata?.artifacts;
            if (message.role === 'assistant' && message.id && Array.isArray(stored) && stored.length > 0) {
                artifacts.value.set(message.id, stored);
            }
        }
    }

    /** Sources are a non-blocking extra: the chat works without them. */
    async function loadSources(): Promise<void> {
        try {
            const byMessage = await fetchConversationSources(conversationId);
            for (const message of messages.value) {
                if (message.role === 'assistant' && message.id && byMessage[message.id]) {
                    sources.value.set(message.id, byMessage[message.id]);
                }
            }
        } catch {
            // Ignored on purpose (see above).
        }
    }

    function sourcesFor(message: Message): MessageSource[] {
        return message.id ? (sources.value.get(message.id) ?? []) : [];
    }

    function artifactsFor(message: Message): ToolArtifact[] {
        return message.id ? (artifacts.value.get(message.id) ?? []) : [];
    }

    function setSources(assistantMessageId: MessageId, list: MessageSource[]) {
        sources.value.set(assistantMessageId, list);
    }

    /** One turn can call several chart tools, so artifacts are appended, not replaced. */
    function addArtifacts(assistantMessageId: MessageId, list: ToolArtifact[]) {
        const existing = artifacts.value.get(assistantMessageId) ?? [];
        artifacts.value.set(assistantMessageId, [...existing, ...list]);
    }

    function isMediaArtifact(artifact: ToolArtifact): artifact is ToolArtifact & { data: GeneratedMediaArtifactData } {
        return GENERATED_MEDIA_KINDS.includes(artifact.kind) && typeof (artifact.data as GeneratedMediaArtifactData | null)?.id === 'string';
    }

    /** The generated images and audio still being made, by id. */
    const pendingMediaIds = computed(() =>
        [...artifacts.value.values()]
            .flat()
            .filter(isMediaArtifact)
            .filter((artifact) => (artifact.data.status ?? 'pending') === 'pending')
            .map((artifact) => artifact.data.id),
    );

    /** Swaps in a generated file's new state wherever its artifact shows. */
    function updateMedia(media: GeneratedMedia) {
        for (const [messageId, list] of artifacts.value) {
            if (!list.some((artifact) => isMediaArtifact(artifact) && artifact.data.id === media.id)) continue;

            artifacts.value.set(
                messageId,
                list.map((artifact) =>
                    isMediaArtifact(artifact) && artifact.data.id === media.id ? { ...artifact, data: { ...artifact.data, ...media } } : artifact,
                ),
            );
        }
    }

    /** Forgets the sources and charts of a reply that is being replaced or deleted. */
    function forgetReplyExtras(assistantMessageId: MessageId | null | undefined) {
        if (!assistantMessageId) return;
        sources.value.delete(assistantMessageId);
        artifacts.value.delete(assistantMessageId);
    }

    /** Appends an optimistic prompt plus an empty reply stub (id 0 until the stream starts). */
    function appendTurn(content: string, attachments: ChatAttachment[]): TurnSlots {
        const userIndex = messages.value.length;
        messages.value.push({ role: 'user', content, id: 0, attachments });
        const assistantIndex = messages.value.length;
        messages.value.push(assistantStub());

        return { userIndex, assistantIndex };
    }

    /** Replaces the message at `index` with an empty reply stub. */
    function resetReply(index: number) {
        messages.value[index] = assistantStub();
    }

    /**
     * The slot for a new reply to the prompt at `userIndex`: the row after it
     * (an error card or an unsaved reply), or a stub inserted there when the
     * next row is another prompt or there is none.
     */
    function replySlotAfter(userIndex: number): number {
        const index = userIndex + 1;
        if (messages.value[index] && messages.value[index].role !== 'user') {
            resetReply(index);
        } else {
            messages.value.splice(index, 0, assistantStub());
        }

        return index;
    }

    /** The reply being streamed: by UUID once the server sent it, else by its slot. */
    function findStreamingAssistant(id: MessageId | null, fallbackIndex: number): Message | undefined {
        if (id) {
            const byId = messages.value.find((message) => message.id === id);
            if (byId) return byId;
        }

        return messages.value[fallbackIndex];
    }

    function indexOf(messageId: MessageId | null | undefined): number {
        return messages.value.findIndex((message) => message.id === messageId);
    }

    /**
     * Removes a message together with its partner (a prompt and its reply are
     * deleted as a pair on the server). Returns true when the transcript is empty.
     */
    function removePair(messageId: MessageId): boolean {
        const index = indexOf(messageId);
        if (index === -1) return false;

        const message = messages.value[index];
        if (message.role === 'user') {
            const reply = messages.value[index + 1];
            const hasReply = reply?.role === 'assistant';
            if (hasReply) forgetReplyExtras(reply.id);
            messages.value.splice(index, hasReply ? 2 : 1);
        } else {
            const prompt = messages.value[index - 1];
            forgetReplyExtras(message.id);
            if (prompt?.role === 'user') {
                messages.value.splice(index - 1, 2);
            } else {
                messages.value.splice(index, 1);
            }
        }

        return messages.value.length === 0;
    }

    /**
     * Drops an attachment chip. Attachments live on the message itself and, for
     * freshly sent messages, also under metadata; both copies go so the chip
     * does not come back on the next render.
     */
    function removeAttachment(messageId: MessageId, attachmentId: string) {
        const message = messages.value.find((candidate) => candidate.id === messageId);
        if (!message) return;

        if (Array.isArray(message.attachments)) {
            message.attachments = message.attachments.filter((attachment) => attachment.id !== attachmentId);
        }
        if (message.metadata?.attachments) {
            message.metadata.attachments = message.metadata.attachments.filter((attachment) => attachment.id !== attachmentId);
        }
    }

    return {
        messages,
        load,
        loadSources,
        sourcesFor,
        artifactsFor,
        setSources,
        addArtifacts,
        pendingMediaIds,
        updateMedia,
        forgetReplyExtras,
        appendTurn,
        resetReply,
        replySlotAfter,
        findStreamingAssistant,
        indexOf,
        removePair,
        removeAttachment,
    };
}

function assistantStub(): Message {
    return { role: 'assistant', content: '', id: 0 };
}

export type ChatMessages = ReturnType<typeof useChatMessages>;
