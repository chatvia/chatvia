<script setup lang="ts">
/**
 * The chat composer: a growing textarea with file upload, the model picker
 * (when the agent offers a choice), voice input, the voice-conversation
 * button and a send/stop button. Used by the hosted chat's Home and Chat pages; the page
 * decides where uploads go and what happens with the sent message.
 */
import ChatModelPicker from '@/chat/components/ChatModelPicker.vue';
import ComposerAttachmentChips from '@/chat/components/ComposerAttachmentChips.vue';
import { Button } from '@/shared/ui/button';
import { Textarea } from '@/shared/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/shared/ui/tooltip';
import { useComposerAttachments } from '@/chat/composables/useComposerAttachments';
import { isVoiceCallSupported } from '@/chat/composables/useRealtimeVoice';
import { useVoiceRecorder } from '@/chat/composables/useVoiceRecorder';
import type { ChatAttachment } from '@/shared/types/chat';
import { ArrowUp, AudioLines, Loader2, Mic, Plus, Square } from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    isThinking?: boolean;
    text?: string;
    placeholder?: string;
    disabled?: boolean;
    /** Endpoint to POST the file to. When set, a "+" upload button appears. */
    uploadEndpoint?: string;
    /** Headers to send with the upload request (e.g. session/thread). */
    uploadHeaders?: Record<string, string>;
    /** Builds the DELETE url for an attachment by id (the parent supplies the
     *  route). When unset, the X button just clears local state. */
    deleteEndpoint?: (id: string) => string;
    /** Accept attribute for the file picker (MIME types); defaults to PDF only. */
    uploadAccept?: string;
    /** Show the "+" / file-upload button. Set false for agents that don't accept uploads. */
    allowFileUploads?: boolean;
    /** Show the mic / speech-to-text button. Set false for agents without voice input. */
    allowVoiceInput?: boolean;
    /** Show the voice-conversation button (a live spoken call with the agent). */
    allowVoiceConversation?: boolean;
    /** Per-chat upload ceiling (`agent.files.upload.max_files`). The server enforces it too. */
    maxFiles?: number;
    /** The longest message the server accepts (`guardrails.max_message_chars`); the textarea stops there. */
    maxLength?: number;
}

const props = withDefaults(defineProps<Props>(), {
    uploadAccept: 'application/pdf',
    allowFileUploads: true,
    allowVoiceInput: true,
    allowVoiceConversation: false,
    maxFiles: 2,
});

const emit = defineEmits<{
    messageAdded: [content: string, attachments: ChatAttachment[]];
    attachmentUploaded: [attachment: ChatAttachment];
    stopStreaming: [];
    startVoice: [];
}>();

const { t } = useI18n();

/** The textarea grows with its content up to this height, then scrolls. */
const MAX_TEXTAREA_HEIGHT_PX = 200;

const userInput = ref(props.text || '');
const messageInput = ref<InstanceType<typeof Textarea> | null>(null);
const fileInput = ref<HTMLInputElement>();
const isFocused = ref(false);

const { isRecording, isTranscribing, isSupported: isVoiceSupported, error: voiceError, startRecording, stopRecording } = useVoiceRecorder();

const {
    attachments,
    isUploading,
    isBusy: isAttachmentBusy,
    canUploadMore,
    limitMessage,
    ensureRoom,
    upload,
    remove: removeAttachment,
    takeAll: takeAttachments,
} = useComposerAttachments({
    uploadEndpoint: toRef(props, 'uploadEndpoint'),
    uploadHeaders: toRef(props, 'uploadHeaders'),
    deleteEndpoint: toRef(props, 'deleteEndpoint'),
    maxFiles: toRef(props, 'maxFiles'),
    onUploaded: (attachment) => emit('attachmentUploaded', attachment),
});

// Also drives the textarea's start padding, so the caret is not flush with
// the edge when the button is hidden.
const showUploadButton = computed(() => props.allowFileUploads && !!props.uploadEndpoint && !props.isThinking && !props.disabled);

const showVoiceCallButton = computed(() => props.allowVoiceConversation && isVoiceCallSupported() && !props.isThinking && !props.disabled);

const canSend = computed(() => (userInput.value.trim() !== '' || attachments.value.length > 0) && !props.disabled && !isAttachmentBusy.value);

function focusInput() {
    messageInput.value?.focus();
}

/** Sizes the textarea to its content (from an input event, or after the text changed in code). */
function adjustTextareaHeight(event?: Event) {
    const textarea = (event?.target as HTMLTextAreaElement | undefined) ?? messageInput.value?.el;
    if (!textarea) return;

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(MAX_TEXTAREA_HEIGHT_PX, textarea.scrollHeight)}px`;
}

// A starter picked on Home fills the composer.
watch(
    () => props.text,
    (newText) => {
        if (newText !== undefined && newText !== userInput.value) {
            userInput.value = newText;
            nextTick(() => adjustTextareaHeight());
        }
    },
    { immediate: true },
);

watch(userInput, () => {
    nextTick(() => adjustTextareaHeight());
});

onMounted(() => {
    nextTick(focusInput);
});

function triggerFilePicker() {
    if (ensureRoom()) fileInput.value?.click();
}

async function onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (file) await upload(file);
}

function onEnter(event: KeyboardEvent) {
    // An IME (e.g. Japanese, Chinese) uses Enter to confirm a word, not to send.
    if (event.shiftKey || event.isComposing) {
        adjustTextareaHeight(event);
        return;
    }

    event.preventDefault();
    sendMessage();
}

/** Esc in the composer stops the reply that is streaming. */
function onEscape() {
    if (props.isThinking) emit('stopStreaming');
}

// Waits for uploads and removals: a send during a removal would still carry the removed file.
// The textarea stays usable while a reply streams, so the next message can be
// typed; it is sent once the reply finished.
function sendMessage() {
    if (props.isThinking) return;
    if (!userInput.value.trim() && attachments.value.length === 0) return;
    if (isAttachmentBusy.value) return;

    emit('messageAdded', userInput.value, takeAttachments());
    userInput.value = '';

    const textarea = messageInput.value?.el;
    if (textarea) textarea.style.height = 'auto';
}

/** The transcript is appended to whatever was already typed; a failure is shown as a toast. */
async function toggleRecording() {
    if (!isRecording.value) {
        await startRecording();
        if (voiceError.value) toast.error(voiceError.value);
        return;
    }

    const transcript = await stopRecording();
    if (voiceError.value) toast.error(voiceError.value);
    if (!transcript) return;

    userInput.value = userInput.value ? `${userInput.value} ${transcript}` : transcript;
    nextTick(() => {
        adjustTextareaHeight();
        focusInput();
    });
}
</script>

<template>
    <div class="w-full">
        <TooltipProvider :delay-duration="200">
            <!-- Single-row composer with a constant 26px radius. Empty, it reads
                 as a compact pill; as the textarea wraps it grows into a rounded
                 rectangle with the same corners — no pill→box morph. items-end
                 keeps the buttons aligned to the last line as the text grows. -->
            <div
                class="bg-card relative flex flex-col rounded-[26px] border transition-[border-color,box-shadow] duration-200"
                :class="[
                    disabled ? 'cursor-not-allowed opacity-60' : '',
                    isFocused && !disabled
                        ? 'border-primary/40 ring-primary/10 shadow-lg ring-1'
                        : 'dark:border-input hover:border-muted-foreground/40 border-[#dcdcdc] shadow-[0_2px_8px_rgba(0,0,0,0.05),0_1px_2px_rgba(0,0,0,0.04)]',
                    isThinking && !isFocused ? 'border-primary/40' : '',
                ]"
            >
                <!-- Attachment chips sit above the input row when present. -->
                <ComposerAttachmentChips
                    v-if="attachments.length > 0 || isUploading"
                    :attachments="attachments"
                    :uploading="isUploading"
                    @remove="removeAttachment"
                />

                <!-- Input row: attach · textarea · voice + send/stop, all on one
                     line. The textarea (flex-1) pushes the actions to the end. -->
                <div class="flex items-end gap-1 p-2">
                    <!-- Left: file upload "+" button. Hidden when the agent disables
                         uploads or while streaming. Disabled (greyed) once the local
                         attachments list hits the per-chat ceiling; the click handler
                         also re-checks and surfaces a toast as a fallback. -->
                    <Tooltip v-if="showUploadButton">
                        <TooltipTrigger as-child>
                            <Button
                                @click="triggerFilePicker"
                                :disabled="isUploading || !canUploadMore"
                                size="icon"
                                variant="ghost"
                                class="hover:bg-muted text-muted-foreground hover:text-foreground h-9 w-9 shrink-0 rounded-full transition-colors"
                                :aria-label="t('composer.attach_file')"
                            >
                                <Plus class="h-5 w-5" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="top">
                            {{ !canUploadMore ? limitMessage() : t('composer.attach_file') }}
                        </TooltipContent>
                    </Tooltip>
                    <input ref="fileInput" type="file" :accept="uploadAccept" class="hidden" @change="onFileSelected" />

                    <!-- Middle: textarea. Grows in place; the row stays single-line
                         until the text actually wraps. -->
                    <Textarea
                        ref="messageInput"
                        v-model="userInput"
                        @keydown.enter="onEnter"
                        @keydown.esc="onEscape"
                        @input="adjustTextareaHeight"
                        @focus="isFocused = true"
                        @blur="isFocused = false"
                        :placeholder="disabled ? t('composer.disabled') : placeholder || t('composer.default_placeholder')"
                        class="text-foreground placeholder:text-muted-foreground/50 max-h-[200px] min-h-[24px] flex-1 resize-none !border-0 !bg-transparent py-2 text-[15px] leading-relaxed !shadow-none focus-visible:!border-0 focus-visible:ring-0 focus-visible:outline-none dark:!bg-transparent"
                        :class="showUploadButton ? 'px-1' : 'px-3'"
                        :disabled="disabled"
                        :maxlength="maxLength"
                        rows="1"
                    />

                    <!-- Right: model picker (when the agent offers a choice), voice + send/stop. -->
                    <div class="flex items-center gap-1">
                        <ChatModelPicker :disabled="disabled || isThinking" />
                        <!-- Voice recording button — hidden when the agent disables voice. -->
                        <Tooltip v-if="allowVoiceInput && isVoiceSupported && !isThinking && !disabled">
                            <TooltipTrigger as-child>
                                <Button
                                    @click="toggleRecording"
                                    :disabled="isTranscribing"
                                    size="icon"
                                    variant="ghost"
                                    class="h-9 w-9 shrink-0 rounded-full transition-colors"
                                    :class="[
                                        isRecording
                                            ? 'animate-pulse bg-red-500 text-white hover:bg-red-600'
                                            : 'hover:bg-muted text-muted-foreground hover:text-foreground',
                                    ]"
                                    :aria-label="isRecording ? t('composer.stop_recording') : t('composer.start_voice_input')"
                                >
                                    <Loader2 v-if="isTranscribing" class="h-5 w-5 animate-spin" />
                                    <Mic v-else class="h-5 w-5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">
                                {{ isRecording ? t('composer.stop_recording') : t('composer.start_voice_input') }}
                            </TooltipContent>
                        </Tooltip>

                        <!-- Voice conversation: opens a live spoken call with the agent. -->
                        <Tooltip v-if="showVoiceCallButton">
                            <TooltipTrigger as-child>
                                <Button
                                    @click="emit('startVoice')"
                                    :disabled="isRecording || isTranscribing"
                                    size="icon"
                                    variant="ghost"
                                    class="hover:bg-muted text-muted-foreground hover:text-foreground h-9 w-9 shrink-0 rounded-full transition-colors"
                                    :aria-label="t('composer.start_voice_call')"
                                >
                                    <AudioLines class="h-5 w-5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="top">{{ t('composer.start_voice_call') }}</TooltipContent>
                        </Tooltip>

                        <Button
                            v-if="isThinking"
                            @click="emit('stopStreaming')"
                            size="icon"
                            class="bg-primary text-primary-foreground hover:bg-primary/90 h-9 w-9 shrink-0 rounded-full"
                            :aria-label="t('composer.stop_streaming')"
                        >
                            <Square class="h-4 w-4 fill-current" />
                        </Button>

                        <Button
                            v-else
                            @click="sendMessage"
                            :disabled="!canSend"
                            size="icon"
                            class="h-9 w-9 shrink-0 rounded-full transition-all duration-200"
                            :class="[canSend ? 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-sm' : 'bg-muted text-muted-foreground']"
                            :aria-label="t('composer.send_message')"
                        >
                            <ArrowUp class="h-5 w-5" :class="{ 'stroke-[2.5px]': canSend }" />
                        </Button>
                    </div>
                </div>
            </div>
        </TooltipProvider>
    </div>
</template>
