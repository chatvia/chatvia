<script setup lang="ts">
/**
 * One visitor prompt in the hosted chat: the text bubble with its attachment
 * chips, and hover actions to copy, edit (re-asks the question) or delete the
 * prompt together with its reply.
 */
import { deleteConversationAttachment, deleteMessage as deleteMessageRequest } from '@/api/chat';
import { errorMessage } from '@/api/http';
import MessageAttachmentChip from '@/components/chat/MessageAttachmentChip.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import type { ChatAttachment, Message } from '@/types/chat';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, Edit3, Trash2 } from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

type MessageId = string | number;

const props = defineProps<{
    message: Message;
    assistantMessageId?: MessageId;
    conversationId?: string;
    isRTL?: boolean;
}>();

const emit = defineEmits<{
    messageDeleted: [id: MessageId];
    messageEdited: [userMessageId: MessageId, assistantMessageId: MessageId | undefined, newContent: string];
    attachmentDeleted: [messageId: MessageId, attachmentId: string];
}>();

const { t } = useI18n();
const { copy, copied: isCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });

const isEditing = ref(false);
const editedContent = ref('');

// The server merges attachments onto each message; fresh prompts carry the
// composer's copy, and older ones may only have it under metadata.
const attachments = computed<ChatAttachment[]>(() => props.message.attachments ?? props.message.metadata?.attachments ?? []);

/** Server ids only: a missing id cannot be acted on yet. */
const persistedId = computed<MessageId | null>(() => (props.message.id === undefined || props.message.id === null ? null : props.message.id));

function startEditing() {
    editedContent.value = props.message.content;
    isEditing.value = true;

    nextTick(() => {
        const textarea = document.getElementById(`edit-user-${props.message.id}`) as HTMLTextAreaElement | null;
        textarea?.focus();
        textarea?.select();
    });
}

function cancelEditing() {
    isEditing.value = false;
    editedContent.value = '';
}

// The edit asks again: it replaces the paired reply, or streams the first one
// when the reply never landed (e.g. the page reloaded mid-stream).
function saveEditedMessage() {
    const content = editedContent.value.trim();
    if (!content || persistedId.value === null) return;

    emit('messageEdited', persistedId.value, props.assistantMessageId, content);
    isEditing.value = false;
}

function onEditKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        saveEditedMessage();
    } else if (event.key === 'Escape') {
        cancelEditing();
    }
}

/**
 * The chip disappears immediately; the server delete also removes the stored
 * file, so earlier replies that referenced it can no longer re-read it (the
 * confirmation dialog warns about this).
 */
async function deleteAttachment(attachmentId: string) {
    if (!props.conversationId || persistedId.value === null) return;

    emit('attachmentDeleted', persistedId.value, attachmentId);
    try {
        await deleteConversationAttachment(props.conversationId, attachmentId);
    } catch (error) {
        toast.error(errorMessage(error, t('messages.delete_attachment_failed')));
    }
}

async function deleteMessage() {
    if (persistedId.value === null || !props.conversationId) return;

    try {
        await deleteMessageRequest(props.conversationId, persistedId.value);
        emit('messageDeleted', persistedId.value);
    } catch {
        // The message stays in place, which tells the visitor the delete did not happen.
    }
}
</script>

<template>
    <div class="group flex w-full items-start justify-end gap-3">
        <div class="flex min-w-0 flex-1 flex-col items-end">
            <div v-if="isEditing" class="w-full max-w-2xl">
                <div class="bg-muted rounded-2xl p-4">
                    <Textarea
                        :id="`edit-user-${message.id}`"
                        v-model="editedContent"
                        class="text-foreground min-h-[60px] w-full resize-none !border-0 bg-transparent p-0 text-[15px] leading-relaxed !shadow-none !ring-0 focus:!border-0 focus:!ring-0 focus:!outline-none focus-visible:!ring-0 focus-visible:!outline-none"
                        :rows="3"
                        @keydown="onEditKeydown"
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <Button variant="outline" @click="cancelEditing" class="h-9 rounded-full px-5 text-sm font-medium">{{
                            t('common.cancel')
                        }}</Button>
                        <Button
                            @click="saveEditedMessage"
                            class="bg-primary text-primary-foreground hover:bg-primary/90 h-9 rounded-full px-5 text-sm font-medium"
                        >
                            {{ t('messages.send') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Attachment chips sit below the text: the visitor just sent them after typing. -->
            <div v-else class="flex max-w-[75%] flex-col items-end gap-2">
                <div v-if="message.content?.trim()" class="bg-primary text-primary-foreground rounded-2xl rounded-ee-md px-4 py-3 shadow-sm">
                    <p class="text-[15px] leading-relaxed break-words whitespace-pre-wrap" :data-rtl="isRTL">{{ message.content }}</p>
                </div>

                <div v-if="attachments.length > 0" class="flex flex-wrap justify-end gap-2">
                    <MessageAttachmentChip
                        v-for="attachment in attachments"
                        :key="attachment.id"
                        :attachment="attachment"
                        @remove="deleteAttachment"
                    />
                </div>
            </div>

            <div
                v-if="!isEditing"
                class="mt-1 flex items-center gap-0.5 pe-1 transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100 [@media(hover:hover)]:opacity-0"
            >
                <Button
                    size="icon"
                    variant="ghost"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                    :aria-label="t('messages.copy_message')"
                    @click="copy(message.content)"
                >
                    <Check v-if="isCopied" class="h-3.5 w-3.5 text-green-500" />
                    <Copy v-else class="h-3.5 w-3.5" />
                </Button>
                <Button
                    v-if="attachments.length === 0"
                    size="icon"
                    variant="ghost"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                    :aria-label="t('messages.edit_message')"
                    @click="startEditing"
                >
                    <Edit3 class="h-3.5 w-3.5" />
                </Button>
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <Button
                            size="icon"
                            variant="ghost"
                            class="text-muted-foreground h-7 w-7 hover:bg-red-500/10 hover:text-red-600"
                            :aria-label="t('common.delete')"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent class="rounded-2xl">
                        <AlertDialogHeader>
                            <AlertDialogTitle>{{ t('messages.delete_user_message_title') }}</AlertDialogTitle>
                            <AlertDialogDescription>{{ t('messages.delete_user_message_description') }}</AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel class="rounded-lg">{{ t('common.cancel') }}</AlertDialogCancel>
                            <AlertDialogAction @click="deleteMessage" class="rounded-lg bg-red-600 hover:bg-red-700">{{
                                t('common.delete')
                            }}</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Right-to-left prompt text, in a system Arabic font when one is installed. */
[data-rtl='true'] {
    text-align: start;
    direction: rtl;
    font-family:
        'Noto Sans Arabic',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}
</style>
