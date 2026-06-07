<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useVoiceRecorder } from '@/composables/useVoiceRecorder';
import axios from 'axios';
import { ArrowUp, FileText, Loader2, Mic, Paperclip, Square, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Attachment {
    id: string;
    url: string;
    filename: string;
    mime_type: string;
    size_bytes: number;
}

interface Props {
    isThinking?: boolean;
    text?: string;
    placeholder?: string;
    disabled?: boolean;
    /** Endpoint to POST the file to. When set, a paperclip button appears. */
    uploadEndpoint?: string;
    /** Headers to send with the upload request (e.g. session/thread). */
    uploadHeaders?: Record<string, string>;
    /** Builds the DELETE url for an attachment by id (Ziggy-resolved by the
     *  parent). When unset, the X button just clears local state. */
    deleteEndpoint?: (id: string) => string;
    /** Accept attribute for the file picker; defaults to PDF only. */
    uploadAccept?: string;
    /** Show the paperclip / file-upload button. Defaults to on. Set false to
     *  hide it for FAQ-only / compliance agents that don't accept uploads. */
    allowFileUploads?: boolean;
    /** Show the mic / speech-to-text button. Defaults to on. Set false to
     *  hide it for agents that shouldn't expose voice recording. */
    allowVoiceInput?: boolean;
    /** Per-chat upload ceiling. Mirrors `agent.files.upload.max_files` —
     *  pass it through from `$page.props.maxFilesPerChat`. The composer
     *  greys out the paperclip once the local attachments list hits this,
     *  but the server enforces the same value so a bypass still 422s. */
    maxFiles?: number;
}

const props = withDefaults(defineProps<Props>(), {
    uploadAccept: 'application/pdf',
    allowFileUploads: true,
    allowVoiceInput: true,
    maxFiles: 2,
});
const { t } = useI18n();

const userInput = ref(props.text || '');
const messageInput = ref<HTMLTextAreaElement>();
const isFocused = ref(false);

// Voice recording
const {
    isRecording,
    isTranscribing,
    isSupported: isVoiceSupported,
    startRecording,
    stopRecording,
    cancelRecording: _cancelRecording,
} = useVoiceRecorder();

watch(
    () => props.text,
    (newText) => {
        if (newText !== undefined && newText !== userInput.value) {
            userInput.value = newText;
            nextTick(() => {
                if (messageInput.value) {
                    adjustTextareaHeight();
                }
            });
        }
    },
    { immediate: true },
);

const emit = defineEmits<{
    messageAdded: [content: string, attachments: Attachment[]];
    attachmentUploaded: [attachment: Attachment];
    stopStreaming: [];
}>();

// Attachments
const fileInput = ref<HTMLInputElement>();
const attachments = ref<Attachment[]>([]);
const isUploading = ref(false);
// In-flight DELETE count. We must not let `sendMessage` fire while a delete
// is still in flight: StoreMessageAction claims every unattached file by
// conversation id (it doesn't read the payload's attachment list), so a
// quick X-then-Enter would smuggle a "removed" file into the next turn.
const pendingDeleteCount = ref(0);

// True while the local attachments list is below the per-chat ceiling.
// Server enforces the same value (MaxConversationAttachments), but gating
// here saves a roundtrip and avoids a confusing error toast on the click
// after the user already saw the chips fill up. Tracks only the current
// turn — if the conversation already has files from prior turns, the
// server's 422 surfaces via the catch block below.
const canUploadMore = computed(() => attachments.value.length < props.maxFiles);

const triggerFilePicker = () => {
    if (!canUploadMore.value) {
        toast.error(t('composer.upload_limit_reached', { count: props.maxFiles }, `You can attach up to ${props.maxFiles} files.`));
        return;
    }
    fileInput.value?.click();
};

const handleFileSelected = async (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file || !props.uploadEndpoint) return;

    // Defensive double-check — covers the edge case where another tab or
    // a race appended an attachment between triggerFilePicker() and the
    // OS file dialog returning.
    if (!canUploadMore.value) {
        target.value = '';
        toast.error(t('composer.upload_limit_reached', { count: props.maxFiles }, `You can attach up to ${props.maxFiles} files.`));
        return;
    }

    target.value = '';

    isUploading.value = true;
    const form = new FormData();
    form.append('file', file);

    try {
        const response = await axios.post(props.uploadEndpoint, form, {
            headers: { 'Content-Type': 'multipart/form-data', ...(props.uploadHeaders ?? {}) },
        });
        const attachment = response.data.data as Attachment;
        attachments.value.push(attachment);
        emit('attachmentUploaded', attachment);
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const msg = err.response?.data?.errors?.file?.[0] ?? err.response?.data?.message ?? t('composer.upload_failed', 'Upload failed');
        toast.error(msg);
    } finally {
        isUploading.value = false;
    }
};

const removeAttachment = async (id: string) => {
    const idx = attachments.value.findIndex((a) => a.id === id);
    if (idx === -1) return;
    const att = attachments.value[idx];

    // Optimistic UI: drop the chip first so the click feels instant. If the
    // server delete fails, we put it back — silently leaving it removed
    // client-side would still send the file to the LLM, because
    // StoreMessageAction sweeps every unattached file on the conversation
    // into the next turn regardless of what the client claims is attached.
    attachments.value = attachments.value.filter((a) => a.id !== id);

    if (!props.deleteEndpoint) return;

    pendingDeleteCount.value++;
    try {
        await axios.delete(props.deleteEndpoint(id));
    } catch (e: unknown) {
        attachments.value.splice(idx, 0, att);
        const err = e as { response?: { data?: { message?: string } } };
        const msg = err.response?.data?.message ?? t('composer.delete_failed', 'Could not remove attachment');
        toast.error(msg);
    } finally {
        pendingDeleteCount.value--;
    }
};

const handleEnterKey = (event: KeyboardEvent) => {
    if (!event.shiftKey) {
        event.preventDefault();
        sendMessage();
    } else {
        adjustTextareaHeight(event);
    }
};

const adjustTextareaHeight = (event?: Event) => {
    const textarea = (event?.target as HTMLTextAreaElement) || messageInput.value;
    if (!textarea || !textarea.style) return;

    textarea.style.height = 'auto';
    textarea.style.height = Math.min(160, textarea.scrollHeight) + 'px';
};

watch(userInput, () => {
    nextTick(() => {
        adjustTextareaHeight();
    });
});

const stopStreaming = () => {
    emit('stopStreaming');
};

const sendMessage = () => {
    if (!userInput.value.trim() && attachments.value.length === 0) return;
    if (isUploading.value) return;
    // Block while a remove is still hitting the server (see comment on
    // pendingDeleteCount) — otherwise the server's orphan sweep races us.
    if (pendingDeleteCount.value > 0) return;

    emit('messageAdded', userInput.value, [...attachments.value]);
    userInput.value = '';
    attachments.value = [];

    if (messageInput.value && messageInput.value.style) {
        messageInput.value.style.height = 'auto';
    }
};

const handleFocus = () => {
    isFocused.value = true;
};

const handleBlur = () => {
    isFocused.value = false;
};

onMounted(() => {
    nextTick(() => {
        if (messageInput.value && typeof messageInput.value.focus === 'function') {
            messageInput.value.focus();
        }
    });
});

const canSend = () =>
    (userInput.value.trim() !== '' || attachments.value.length > 0) && !props.disabled && !isUploading.value && pendingDeleteCount.value === 0;

const formatBytes = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
};

// Voice recording handlers
const toggleRecording = async () => {
    if (isRecording.value) {
        const transcript = await stopRecording();
        if (transcript) {
            // Append transcript to existing input
            userInput.value = userInput.value ? `${userInput.value} ${transcript}` : transcript;

            nextTick(() => {
                adjustTextareaHeight();
                if (messageInput.value && typeof messageInput.value.focus === 'function') {
                    messageInput.value.focus();
                }
            });
        }
    } else {
        await startRecording();
    }
};
</script>

<template>
    <div class="w-full">
        <div
            class="bg-card relative rounded-3xl border transition-all duration-200"
            :class="[
                disabled ? 'cursor-not-allowed opacity-50' : '',
                isFocused && !disabled
                    ? 'border-primary/40 ring-primary/10 shadow-lg ring-1'
                    : 'border-gray-300 shadow-sm hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500',
                isThinking ? 'border-primary/40' : '',
            ]"
        >
            <!-- Attachment chips -->
            <div v-if="attachments.length > 0 || isUploading" class="flex flex-wrap gap-2 px-4 pt-3">
                <div
                    v-for="att in attachments"
                    :key="att.id"
                    class="bg-muted text-foreground flex items-center gap-2 rounded-md px-2.5 py-1.5 text-xs"
                >
                    <FileText class="text-muted-foreground h-3.5 w-3.5 shrink-0" />
                    <span class="max-w-[180px] truncate">{{ att.filename }}</span>
                    <span class="text-muted-foreground">{{ formatBytes(att.size_bytes) }}</span>
                    <button
                        type="button"
                        @click="removeAttachment(att.id)"
                        class="text-muted-foreground hover:text-foreground -mr-1 rounded p-0.5"
                        :aria-label="t('composer.remove_attachment', 'Remove attachment')"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
                <div v-if="isUploading" class="text-muted-foreground bg-muted flex items-center gap-2 rounded-md px-2.5 py-1.5 text-xs">
                    <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    {{ t('composer.uploading', 'Uploading…') }}
                </div>
            </div>

            <div class="flex items-center">
                <!-- Left side: textarea -->
                <div class="min-w-0 flex-1">
                    <Textarea
                        ref="messageInput"
                        v-model="userInput"
                        @keydown.enter="handleEnterKey"
                        @input="adjustTextareaHeight"
                        @focus="handleFocus"
                        @blur="handleBlur"
                        :placeholder="disabled ? t('composer.disabled') : placeholder || t('composer.default_placeholder')"
                        class="text-foreground placeholder:text-muted-foreground/50 max-h-[160px] min-h-[28px] w-full resize-none !border-0 !bg-transparent px-4 py-4 text-sm !shadow-none focus-visible:!border-0 focus-visible:ring-0 focus-visible:outline-none dark:!bg-transparent"
                        :disabled="isThinking || disabled"
                        rows="1"
                    />
                </div>

                <!-- Right side: Attach, Mic, and Send/Stop buttons -->
                <div class="flex items-center gap-1.5 p-3">
                    <!-- File upload button — hidden when the agent disables uploads.
                         Disabled (greyed) once the local attachments list hits
                         the per-chat ceiling; the click handler also re-checks
                         and surfaces a toast as a fallback. -->
                    <Button
                        v-if="allowFileUploads && uploadEndpoint && !isThinking && !disabled"
                        @click="triggerFilePicker"
                        :disabled="isUploading || !canUploadMore"
                        size="icon"
                        variant="ghost"
                        class="hover:bg-muted text-muted-foreground hover:text-foreground h-10 w-10 shrink-0 rounded-full transition-all duration-200"
                        :title="
                            !canUploadMore
                                ? t('composer.upload_limit_reached', { count: maxFiles }, `You can attach up to ${maxFiles} files.`)
                                : t('composer.attach_file', 'Attach a PDF')
                        "
                    >
                        <Paperclip class="h-5 w-5" />
                    </Button>
                    <input ref="fileInput" type="file" :accept="uploadAccept" class="hidden" @change="handleFileSelected" />

                    <!-- Voice recording button — hidden when the agent disables voice. -->
                    <Button
                        v-if="allowVoiceInput && isVoiceSupported && !isThinking && !disabled"
                        @click="toggleRecording"
                        :disabled="isTranscribing"
                        size="icon"
                        variant="ghost"
                        class="h-10 w-10 shrink-0 rounded-full transition-all duration-200"
                        :class="[
                            isRecording
                                ? 'animate-pulse bg-red-500 text-white hover:bg-red-600'
                                : 'hover:bg-muted text-muted-foreground hover:text-foreground',
                        ]"
                        :title="isRecording ? t('composer.stop_recording') : t('composer.start_voice_input')"
                    >
                        <Loader2 v-if="isTranscribing" class="h-5 w-5 animate-spin" />
                        <Mic v-else class="h-5 w-5" />
                    </Button>

                    <Button v-if="isThinking" @click="stopStreaming" size="icon" variant="destructive" class="h-10 w-10 shrink-0 rounded-full">
                        <Square class="h-5 w-5" />
                    </Button>

                    <Button
                        v-else
                        @click="sendMessage"
                        :disabled="!canSend()"
                        size="icon"
                        class="h-10 w-10 shrink-0 rounded-full transition-all duration-200"
                        :class="[canSend() ? 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-md' : 'bg-muted text-muted-foreground']"
                    >
                        <ArrowUp class="h-5 w-5" :class="{ 'stroke-[2.5px]': canSend() }" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
