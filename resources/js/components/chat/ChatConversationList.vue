<script setup lang="ts">
/**
 * The "Your chats" list in the hosted chat sidebar: links to each
 * conversation, inline rename, and a confirmed delete. The parent owns the
 * data and performs the requests (rename through a prop, delete as an event).
 */
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useCurrentRoute } from '@/composables/useCurrentRoute';
import { show as showChat } from '@/routes/chat';
import type { ChatConversationSummary } from '@/types/chat';
import { Link } from '@inertiajs/vue3';
import { Check, Edit, MoreVertical, Trash2, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

type ConversationId = string | number;

const props = defineProps<{
    conversations: ChatConversationSummary[];
    loading: boolean;
    /** Saves a new title; the input stays open until it settles. */
    rename: (id: ConversationId, title: string) => Promise<unknown>;
}>();

const emit = defineEmits<{
    delete: [id: ConversationId];
}>();

const { current: isCurrentRoute } = useCurrentRoute();

const { t } = useI18n();

/** Titles at least this long are truncated, so they get a tooltip with the full text. */
const LONG_TITLE_LENGTH = 30;

const editingId = ref<ConversationId | null>(null);
const editingTitle = ref('');
const deleteDialogOpen = ref(false);
const conversationToDelete = ref<ConversationId | null>(null);

function startEditing(conversation: ChatConversationSummary) {
    editingId.value = conversation.id;
    editingTitle.value = conversation.title;

    nextTick(() => {
        const input = document.getElementById(`edit-conversation-${conversation.id}`) as HTMLInputElement | null;
        input?.focus();
        input?.select();
    });
}

function cancelEditing() {
    editingId.value = null;
    editingTitle.value = '';
}

async function saveEditing() {
    const title = editingTitle.value.trim();
    if (!editingId.value || !title) return;

    try {
        await props.rename(editingId.value, title);
    } finally {
        cancelEditing();
    }
}

function onEditKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter') saveEditing();
    else if (event.key === 'Escape') cancelEditing();
}

function askToDelete(id: ConversationId) {
    conversationToDelete.value = id;
    deleteDialogOpen.value = true;
}

function confirmDelete() {
    if (conversationToDelete.value) emit('delete', conversationToDelete.value);
    closeDeleteDialog();
}

function closeDeleteDialog() {
    deleteDialogOpen.value = false;
    conversationToDelete.value = null;
}
</script>

<template>
    <ScrollArea class="h-full flex-1">
        <div v-if="loading" class="flex justify-center py-8">
            <div class="border-muted-foreground/20 border-t-muted-foreground h-5 w-5 animate-spin rounded-full border-2"></div>
        </div>

        <div v-else-if="conversations.length === 0" class="px-3 py-8 text-center">
            <p class="text-muted-foreground/60 text-sm">{{ t('sidebar.no_conversations') }}</p>
        </div>

        <div v-else class="space-y-0.5 px-1">
            <div v-for="chat in conversations" :key="chat.id" class="group relative">
                <div v-if="editingId !== chat.id">
                    <TooltipProvider>
                        <Tooltip :disabled="chat.title.length < LONG_TITLE_LENGTH">
                            <TooltipTrigger asChild>
                                <Link
                                    :class="[
                                        'flex w-full cursor-pointer items-center rounded-lg px-3 py-2.5 text-sm transition-colors',
                                        isCurrentRoute('chat.show', { conversationId: chat.id })
                                            ? 'text-foreground bg-sidebar-accent'
                                            : 'text-foreground/80 hover:bg-sidebar-accent/60',
                                    ]"
                                    :href="showChat.url(chat.id)"
                                >
                                    <span class="flex-1 truncate">{{ chat.title }}</span>
                                </Link>
                            </TooltipTrigger>
                            <TooltipContent v-if="chat.title.length >= LONG_TITLE_LENGTH" side="right" :sideOffset="10">
                                {{ chat.title }}
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>

                    <div
                        class="bg-accent/80 absolute end-1 top-1/2 flex -translate-y-1/2 items-center gap-0.5 rounded-md px-0.5 opacity-0 transition-opacity group-hover:opacity-100"
                    >
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button :aria-label="t('shared.more_options')" class="h-7 w-7" size="icon" variant="ghost" @click.prevent.stop>
                                    <MoreVertical class="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="w-40">
                                <DropdownMenuItem class="cursor-pointer" @click="startEditing(chat)">
                                    <Edit class="me-2 h-4 w-4" />
                                    <span>{{ t('sidebar.rename_conversation') }}</span>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem class="text-destructive focus:text-destructive cursor-pointer" @click="askToDelete(chat.id)">
                                    <Trash2 class="me-2 h-4 w-4" />
                                    <span>{{ t('common.delete') }}</span>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div v-else class="bg-accent flex w-full items-center gap-2 rounded-lg px-2 py-1.5">
                    <Input
                        :id="`edit-conversation-${chat.id}`"
                        v-model="editingTitle"
                        class="h-8 flex-1 text-sm"
                        @blur="saveEditing"
                        @keydown="onEditKeydown"
                    />
                    <Button
                        class="h-7 w-7 text-green-600 hover:bg-green-500/10"
                        size="icon"
                        variant="ghost"
                        :aria-label="t('sidebar.save_title')"
                        @click="saveEditing"
                    >
                        <Check class="h-4 w-4" />
                    </Button>
                    <Button
                        class="text-muted-foreground hover:text-foreground h-7 w-7"
                        size="icon"
                        variant="ghost"
                        :aria-label="t('common.cancel')"
                        @click="cancelEditing"
                    >
                        <X class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </ScrollArea>

    <AlertDialog v-model:open="deleteDialogOpen">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('sidebar.delete_conversation_title') }}</AlertDialogTitle>
                <AlertDialogDescription>{{ t('sidebar.delete_conversation_description') }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel @click="closeDeleteDialog">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="confirmDelete">
                    {{ t('common.delete') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
