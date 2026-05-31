<script setup lang="ts">
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
import type { Message } from '@/types/chat';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, Copy, Edit3, Trash2 } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    message: Message;
    assistantMessageId?: string | number;
    conversationId?: string;
    isRTL?: boolean;
}>();

const emit = defineEmits<{
    messageUpdated: [id: string | number, content: string];
    messageDeleted: [id: string | number];
    messageEdited: [userMessageId: string | number, assistantMessageId: string | number, newContent: string];
}>();

const page = usePage();
const _user = (page.props as any).auth?.user;

const isEditing = ref(false);
const editedContent = ref('');
const isCopied = ref(false);

function startEditing() {
    editedContent.value = props.message.content;
    isEditing.value = true;

    nextTick(() => {
        const textarea = document.getElementById(`edit-user-${props.message.id}`);
        if (textarea) {
            (textarea as HTMLTextAreaElement).focus();
            (textarea as HTMLTextAreaElement).select();
        }
    });
}

function cancelEditing() {
    isEditing.value = false;
    editedContent.value = '';
}

function saveEditedMessage() {
    if (!editedContent.value.trim() || props.message.id === undefined) return;

    // If assistant message ID exists, emit messageEdited for regeneration flow
    if (props.assistantMessageId !== undefined) {
        emit('messageEdited', props.message.id, props.assistantMessageId, editedContent.value.trim());
    } else {
        // Fallback to old behavior
        emit('messageUpdated', props.message.id, editedContent.value.trim());
    }
    isEditing.value = false;
}

function handleEditKeyDown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        saveEditedMessage();
    } else if (event.key === 'Escape') {
        cancelEditing();
    }
}

async function copyToClipboard() {
    try {
        await navigator.clipboard.writeText(props.message.content);
        isCopied.value = true;
        setTimeout(() => {
            isCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy text:', err);
    }
}

async function deleteMessage() {
    if (props.message.id !== undefined && props.conversationId) {
        try {
            await axios.delete(route('chat.messages.delete'), {
                data: {
                    conversation_id: props.conversationId,
                    message_id: props.message.id,
                },
            });

            console.log('ChatMessage deleted successfully:', props.message.id);
            emit('messageDeleted', props.message.id);
        } catch (error) {
            console.error('Failed to delete message:', error);
        }
    }
}
</script>

<template>
    <div class="group flex w-full items-start justify-end gap-3">
        <div class="flex min-w-0 flex-1 flex-col items-end">
            <!-- Edit mode - full width card -->
            <div v-if="isEditing" class="w-full max-w-2xl">
                <div class="bg-muted rounded-2xl p-4">
                    <Textarea
                        :id="`edit-user-${message.id}`"
                        v-model="editedContent"
                        class="text-foreground min-h-[60px] w-full resize-none !border-0 bg-transparent p-0 text-[15px] leading-relaxed !shadow-none !ring-0 focus:!border-0 focus:!ring-0 focus:!outline-none focus-visible:!ring-0 focus-visible:!outline-none"
                        :rows="3"
                        @keydown="handleEditKeyDown"
                    />
                    <div class="mt-4 flex justify-end gap-2">
                        <Button variant="outline" @click="cancelEditing" class="h-9 rounded-full px-5 text-sm font-medium">{{
                            t('common.cancel')
                        }}</Button>
                        <Button
                            @click="saveEditedMessage"
                            class="bg-foreground text-background hover:bg-foreground/90 h-9 rounded-full px-5 text-sm font-medium"
                        >
                            {{ t('messages.send') }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Normal display mode -->
            <div v-else class="bg-primary text-primary-foreground max-w-[75%] rounded-2xl rounded-br-md px-4 py-3 shadow-sm">
                <p class="text-[15px] leading-relaxed break-words whitespace-pre-wrap" :data-rtl="isRTL">{{ message.content }}</p>
            </div>

            <div v-if="!isEditing" class="mt-1 flex items-center gap-0.5 pr-1 opacity-0 transition-opacity duration-150 group-hover:opacity-100">
                <Button
                    size="icon"
                    variant="ghost"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                    @click="copyToClipboard"
                >
                    <Check v-if="isCopied" class="h-3.5 w-3.5 text-green-500" />
                    <Copy v-else class="h-3.5 w-3.5" />
                </Button>
                <Button
                    size="icon"
                    variant="ghost"
                    class="text-muted-foreground hover:text-foreground hover:bg-muted/80 h-7 w-7"
                    @click="startEditing"
                >
                    <Edit3 class="h-3.5 w-3.5" />
                </Button>
                <AlertDialog>
                    <AlertDialogTrigger asChild>
                        <Button size="icon" variant="ghost" class="text-muted-foreground h-7 w-7 hover:bg-red-500/10 hover:text-red-600">
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
/* RTL Support for user message content */
[data-rtl='true'] {
    text-align: right;
    direction: rtl;
    font-family:
        'Noto Sans Arabic',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}

/* Arabic Font Support for RTL content */
[data-rtl='true'] p {
    font-family:
        'Noto Sans Arabic',
        -apple-system,
        BlinkMacSystemFont,
        'Segoe UI',
        Roboto,
        sans-serif;
}
</style>
