<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
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
} from '@/components/ui/alert-dialog'
import { Copy, Check, Edit3, Trash2 } from 'lucide-vue-next'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import type { Message } from '@/types/chat'

const props = defineProps<{
  message: Message
  assistantMessageId?: string | number
  conversationId?: string
  isRTL?: boolean
}>()

const emit = defineEmits<{
  messageUpdated: [id: string | number, content: string]
  messageDeleted: [id: string | number]
  messageEdited: [userMessageId: string | number, assistantMessageId: string | number, newContent: string]
}>()

const page = usePage()
const user = (page.props as any).auth?.user

const isEditing = ref(false)
const editedContent = ref('')
const isCopied = ref(false)

function startEditing() {
  editedContent.value = props.message.content
  isEditing.value = true

  nextTick(() => {
    const textarea = document.getElementById(`edit-user-${props.message.id}`)
    if (textarea) {
      ;(textarea as HTMLTextAreaElement).focus()
      ;(textarea as HTMLTextAreaElement).select()
    }
  })
}

function cancelEditing() {
  isEditing.value = false
  editedContent.value = ''
}

function saveEditedMessage() {
  if (!editedContent.value.trim() || props.message.id === undefined) return

  // If assistant message ID exists, emit messageEdited for regeneration flow
  if (props.assistantMessageId !== undefined) {
    emit('messageEdited', props.message.id, props.assistantMessageId, editedContent.value.trim())
  } else {
    // Fallback to old behavior
    emit('messageUpdated', props.message.id, editedContent.value.trim())
  }
  isEditing.value = false
}

function handleEditKeyDown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    saveEditedMessage()
  } else if (event.key === 'Escape') {
    cancelEditing()
  }
}

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(props.message.content)
    isCopied.value = true
    setTimeout(() => {
      isCopied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy text:', err)
  }
}

async function deleteMessage() {
  if (props.message.id !== undefined && props.conversationId) {
    try {
      await axios.delete(route('chat.messages.delete'), {
        data: {
          conversation_id: props.conversationId,
          message_id: props.message.id
        }
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
  <div class="flex justify-end items-start gap-3 group w-full">
    <div class="flex flex-col items-end flex-1 min-w-0">
      <!-- Edit mode - full width card -->
      <div v-if="isEditing" class="w-full max-w-2xl">
        <div class="bg-muted rounded-2xl p-4">
          <Textarea
            :id="`edit-user-${message.id}`"
            v-model="editedContent"
            class="w-full min-h-[60px] bg-transparent !border-0 !shadow-none !ring-0 resize-none text-foreground text-[15px] leading-relaxed focus:!ring-0 focus:!outline-none focus:!border-0 focus-visible:!ring-0 focus-visible:!outline-none p-0"
            :rows="3"
            @keydown="handleEditKeyDown"
          />
          <div class="flex justify-end gap-2 mt-4">
            <Button
              variant="outline"
              @click="cancelEditing"
              class="rounded-full px-5 h-9 text-sm font-medium"
            >
              Cancel
            </Button>
            <Button
              @click="saveEditedMessage"
              class="rounded-full px-5 h-9 text-sm font-medium bg-foreground text-background hover:bg-foreground/90"
            >
              Send
            </Button>
          </div>
        </div>
      </div>

      <!-- Normal display mode -->
      <div v-else class="px-4 py-3 bg-primary text-primary-foreground rounded-2xl rounded-br-md max-w-[75%] shadow-sm">
        <p class="text-[15px] leading-relaxed whitespace-pre-wrap break-words" :data-rtl="isRTL">{{ message.content }}</p>
      </div>

      <div v-if="!isEditing" class="flex items-center gap-0.5 mt-1 pr-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150">
        <Button
          size="icon"
          variant="ghost"
          class="h-7 w-7 text-muted-foreground hover:text-foreground hover:bg-muted/80"
          @click="copyToClipboard"
        >
          <Check v-if="isCopied" class="h-3.5 w-3.5 text-green-500" />
          <Copy v-else class="h-3.5 w-3.5" />
        </Button>
        <Button
          size="icon"
          variant="ghost"
          class="h-7 w-7 text-muted-foreground hover:text-foreground hover:bg-muted/80"
          @click="startEditing"
        >
          <Edit3 class="h-3.5 w-3.5" />
        </Button>
        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button
              size="icon"
              variant="ghost"
              class="h-7 w-7 text-muted-foreground hover:text-red-600 hover:bg-red-500/10"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent class="rounded-2xl">
            <AlertDialogHeader>
              <AlertDialogTitle>Delete this message?</AlertDialogTitle>
              <AlertDialogDescription>
                This will also delete the AI response.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel class="rounded-lg">Cancel</AlertDialogCancel>
              <AlertDialogAction @click="deleteMessage" class="bg-red-600 hover:bg-red-700 rounded-lg">
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* RTL Support for user message content */
[data-rtl="true"] {
  text-align: right;
  direction: rtl;
  font-family: 'Noto Sans Arabic', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Arabic Font Support for RTL content */
[data-rtl="true"] p {
  font-family: 'Noto Sans Arabic', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
</style>
