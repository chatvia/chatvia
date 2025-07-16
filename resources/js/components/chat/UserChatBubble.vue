<script setup lang="ts">
import { ref, nextTick } from 'vue'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Card } from '@/components/ui/card'
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
import { Copy, Check, Edit3, X, Send, Trash2 } from 'lucide-vue-next'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import type { Message } from '@/types/chat'

const props = defineProps<{
  message: Message
  conversationId?: string
}>()

const emit = defineEmits<{
  messageUpdated: [id: string | number, content: string]
  messageDeleted: [id: string | number]
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
  if (!editedContent.value.trim() || !props.message.id) return
  
  emit('messageUpdated', props.message.id, editedContent.value.trim())
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
      
      console.log('Message deleted successfully:', props.message.id);
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
      <Card class="p-4 bg-primary text-primary-foreground border-0 rounded-2xl rounded-tr-sm max-w-[70%]">
        <div v-if="isEditing" class="min-w-[300px]">
          <Textarea
            :id="`edit-user-${message.id}`"
            v-model="editedContent"
            class="min-h-[80px] bg-primary-foreground/10 text-primary-foreground border-0 focus:ring-2 focus:ring-primary-foreground/20"
            @keydown="handleEditKeyDown"
          />
          <div class="flex justify-end gap-2 mt-3">
            <Button
              size="sm"
              variant="ghost"
              class="text-primary-foreground hover:text-primary-foreground hover:bg-primary-foreground/10"
              @click="cancelEditing"
            >
              <X class="w-4 h-4 mr-1" />
              Cancel
            </Button>
            <Button
              size="sm"
              variant="secondary"
              @click="saveEditedMessage"
            >
              <Send class="w-4 h-4 mr-1" />
              Send
            </Button>
          </div>
        </div>
        <div v-else>
          <p class="text-sm whitespace-pre-wrap break-words">{{ message.content }}</p>
        </div>
      </Card>
      
      <div class="flex items-center gap-1 mt-2 opacity-0 group-hover:opacity-100 transition-opacity">
        <Button
          size="icon"
          variant="ghost"
          class="h-8 w-8"
          @click="copyToClipboard"
        >
          <Check v-if="isCopied" class="h-4 w-4 text-green-500" />
          <Copy v-else class="h-4 w-4" />
        </Button>
        <Button
          v-if="!isEditing"
          size="icon"
          variant="ghost"
          class="h-8 w-8"
          @click="startEditing"
        >
          <Edit3 class="h-4 w-4" />
        </Button>
        <AlertDialog>
          <AlertDialogTrigger asChild>
            <Button
              v-if="!isEditing"
              size="icon"
              variant="ghost"
              class="h-8 w-8 hover:text-red-600"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Delete this message?</AlertDialogTitle>
              <AlertDialogDescription>
                This action cannot be undone. The message and any subsequent response will be permanently deleted.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Cancel</AlertDialogCancel>
              <AlertDialogAction @click="deleteMessage" class="bg-red-600 hover:bg-red-700">
                Delete
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </div>
  </div>
</template> 