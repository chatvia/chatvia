<script setup lang="ts">
import { ref, watch, nextTick, onMounted, computed } from 'vue'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
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
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Copy,
  Check,
  ThumbsUp,
  ThumbsDown,
  Trash2,
  Download,
  FileText,
  MessageSquare,
  Bot,
  Sparkles
} from 'lucide-vue-next'
import { usePage } from '@inertiajs/vue3'
import { Marked } from 'marked'
import markedAlert from 'marked-alert'
import markedCodePreview from 'marked-code-preview'
import { markedHighlight } from 'marked-highlight'
import markedKatex from 'marked-katex-extension'
import hljs from 'highlight.js'
import WaitingIndicator from './WaitingIndicator.vue'
import type { Message, Model } from '@/types/chat'

const props = defineProps<{
  message: Message
  isThinking?: boolean
  hideFeedback?: boolean
}>()

const emit = defineEmits<{
  messageDeleted: [id: string | number]
  feedbackProvided: [id: string | number, type: 'thumbs-up' | 'thumbs-down']
}>()

const page = usePage()
const modelUsed = computed(() => {
    const models = page.props.models as Model[]
    const defaultModel = page.props.defaultModel as Model
    return models.find((m) => m.external_id === props.message.model) || defaultModel
})

const messageContentRef = ref<HTMLElement>()
const isCopied = ref(false)
const feedback = ref<'thumbs-up' | 'thumbs-down' | null>(null)

const marked = new Marked(
  markedHighlight({
    emptyLangClass: 'hljs',
    langPrefix: 'hljs language-',
    highlight(code, lang) {
      const language = hljs.getLanguage(lang) ? lang : 'plaintext'
      return hljs.highlight(code, { language }).value
    },
  }),
)

marked.use({ gfm: true })
  .use(markedKatex())
  .use(markedCodePreview())
  .use(markedAlert())

function formatMessage(content: string): string {
  return marked.parse(content)
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

function provideFeedback(type: 'thumbs-up' | 'thumbs-down') {
  if (feedback.value === type) {
    feedback.value = null
  } else {
    feedback.value = type
  }
  emit('feedbackProvided', props.message.id, type)
}

function deleteMessage() {
  emit('messageDeleted', props.message.id)
}

function exportAs(format: 'pdf' | 'markdown' | 'text') {
  // TODO: Implement export functionality
  console.log(`Exporting as ${format}`)
}

function setupCodeBlockCopyButtons() {
  if (!messageContentRef.value) return

  const codeBlocks = messageContentRef.value.querySelectorAll('pre')
  codeBlocks.forEach((pre) => {
    if (pre.querySelector('.code-copy-button')) return

    const copyButton = document.createElement('button')
    copyButton.className = 'code-copy-button'
    copyButton.setAttribute('aria-label', 'Copy code to clipboard')

    const copyIcon = document.createElement('span')
    copyIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>`
    copyButton.appendChild(copyIcon)

    copyButton.addEventListener('click', async (e) => {
      e.preventDefault()
      e.stopPropagation()

      const code = pre.querySelector('code')
      if (code) {
        try {
          await navigator.clipboard.writeText(code.textContent || '')
          copyButton.classList.add('copied')
          copyIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`

          setTimeout(() => {
            copyButton.classList.remove('copied')
            copyIcon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>`
          }, 2000)
        } catch (err) {
          console.error('Failed to copy code:', err)
        }
      }
    })

    pre.style.position = 'relative'
    pre.appendChild(copyButton)
  })
}

watch(() => props.message.content, () => {
  if (props.message.content) {
    nextTick(() => setupCodeBlockCopyButtons())
  }
}, { immediate: true })

onMounted(() => {
  if (props.message.content) {
    setupCodeBlockCopyButtons()
  }
})
</script>

<template>
  <div class="flex items-start gap-3 group w-full">
    <Avatar class="h-8 w-8 flex-shrink-0">
      <AvatarFallback class="bg-gradient-to-br from-violet-500 to-purple-600">
        <Bot class="h-4 w-4 text-white" />
      </AvatarFallback>
    </Avatar>

    <div class="flex-1 min-w-0 max-w-full">
      <div class="prose prose-sm dark:prose-invert max-w-none w-full">
        <div v-if="message.content"
             id="chat-ai-responses"
             ref="messageContentRef"
             v-html="formatMessage(message.content)">
        </div>
        <div v-else class="flex items-center gap-2">
          <WaitingIndicator />
        </div>
      </div>

      <div v-if="message.content && !hideFeedback"
           class="mt-3 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-1">
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
            size="icon"
            variant="ghost"
            class="h-8 w-8"
            :class="{ 'text-green-600 bg-green-50 dark:bg-green-950': feedback === 'thumbs-up' }"
            @click="provideFeedback('thumbs-up')"
          >
            <ThumbsUp class="h-4 w-4" />
          </Button>

          <Button
            size="icon"
            variant="ghost"
            class="h-8 w-8"
            :class="{ 'text-red-600 bg-red-50 dark:bg-red-950': feedback === 'thumbs-down' }"
            @click="provideFeedback('thumbs-down')"
          >
            <ThumbsDown class="h-4 w-4" />
          </Button>

          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button
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
                  This action cannot be undone. The message will be permanently deleted.
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

          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <Button
                size="icon"
                variant="ghost"
                class="h-8 w-8"
              >
                <Download class="h-4 w-4" />
              </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
              <DropdownMenuLabel>Export as</DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem @click="exportAs('pdf')">
                <FileText class="mr-2 h-4 w-4 text-red-500" />
                PDF
              </DropdownMenuItem>
              <DropdownMenuItem @click="exportAs('markdown')">
                <MessageSquare class="mr-2 h-4 w-4 text-blue-500" />
                Markdown
              </DropdownMenuItem>
              <DropdownMenuItem @click="exportAs('text')">
                <FileText class="mr-2 h-4 w-4 text-gray-500" />
                Text
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>

        <Badge variant="secondary" class="flex items-center gap-1">
          <img
            :src="`/images/llms/${modelUsed.icon}`"
            :alt="modelUsed.name"
            class="h-3 w-3"
          />
          <span class="text-xs">{{ modelUsed.name }}</span>
        </Badge>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Prose customizations */
:deep(.prose) {
  font-size: 1rem;
  line-height: 1.75rem;
  color: hsl(var(--foreground));
}

:deep(.prose p) {
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
}

:deep(.prose p:first-child) {
  margin-top: 0;
}

:deep(.prose p:last-child) {
  margin-bottom: 0;
}

:deep(.prose pre) {
  background-color: hsl(var(--muted));
  border: 1px solid hsl(var(--border));
  margin: 0.75rem 0;
  position: relative;
  border-radius: 0.5rem;
}

:deep(.prose code) {
  font-size: 0.8rem;
}

:deep(.prose :not(pre) > code) {
  background-color: hsl(var(--muted));
  padding: 0.125rem 0.25rem;
  border-radius: 0.25rem;
  font-size: 0.8rem;
}

/* Code copy button */
:deep(.code-copy-button) {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  padding: 0.25rem;
  background-color: hsl(var(--background));
  border: 1px solid hsl(var(--border));
  border-radius: 0.25rem;
  color: hsl(var(--muted-foreground));
  cursor: pointer;
  opacity: 0;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

:deep(pre:hover .code-copy-button) {
  opacity: 1;
}

:deep(.code-copy-button:hover) {
  background-color: hsl(var(--accent));
  color: hsl(var(--accent-foreground));
}

:deep(.code-copy-button.copied) {
  background-color: hsl(var(--success));
  color: white;
  border-color: hsl(var(--success));
}

/* Alert styling */
:deep(.markdown-alert) {
  padding: 0.75rem 1rem;
  margin: 0.75rem 0;
  border-left-width: 4px;
  border-left-style: solid;
  border-radius: 0.375rem;
}

:deep(.markdown-alert-note) {
  border-left-color: hsl(var(--primary));
  background-color: hsl(var(--primary) / 0.1);
}

:deep(.markdown-alert-tip) {
  border-left-color: hsl(var(--success));
  background-color: hsl(var(--success) / 0.1);
}

:deep(.markdown-alert-important) {
  border-left-color: hsl(var(--warning));
  background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-warning) {
  border-left-color: hsl(var(--warning));
  background-color: hsl(var(--warning) / 0.1);
}

:deep(.markdown-alert-caution) {
  border-left-color: hsl(var(--destructive));
  background-color: hsl(var(--destructive) / 0.1);
}

:deep(.markdown-alert-title) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  margin-bottom: 0.25rem;
}

/* Tables */
:deep(.prose table) {
  border-collapse: collapse;
  margin: 1rem 0;
  width: 100%;
}

:deep(.prose thead) {
  border-bottom: 2px solid hsl(var(--border));
}

:deep(.prose th) {
  font-weight: 600;
  padding: 0.75rem;
  text-align: left;
}

:deep(.prose td) {
  padding: 0.75rem;
  border-bottom: 1px solid hsl(var(--border));
}

:deep(.prose tbody tr:last-child td) {
  border-bottom: none;
}

/* Lists */
:deep(.prose ul) {
  list-style-type: disc;
  padding-left: 1.5rem;
}

:deep(.prose ol) {
  list-style-type: decimal;
  padding-left: 1.5rem;
}

:deep(.prose li) {
  margin: 0.25rem 0;
}

/* Blockquotes */
:deep(.prose blockquote) {
  border-left: 3px solid hsl(var(--muted-foreground) / 0.3);
  padding-left: 1rem;
  margin: 1rem 0;
  font-style: italic;
  color: hsl(var(--muted-foreground));
}

/* Links */
:deep(.prose a) {
  color: hsl(var(--primary));
  text-decoration: underline;
  text-underline-offset: 2px;
}

:deep(.prose a:hover) {
  text-decoration: none;
}

/* Headings */
:deep(.prose h1),
:deep(.prose h2),
:deep(.prose h3),
:deep(.prose h4),
:deep(.prose h5),
:deep(.prose h6) {
  font-weight: 600;
  margin-top: 1.5rem;
  margin-bottom: 0.5rem;
}

:deep(.prose h1) { font-size: 1.5rem; }
:deep(.prose h2) { font-size: 1.25rem; }
:deep(.prose h3) { font-size: 1.125rem; }
:deep(.prose h4) { font-size: 1rem; }

/* Images */
:deep(.prose img) {
  border-radius: 0.5rem;
  margin: 1rem 0;
  max-width: 100%;
  height: auto;
}

/* Horizontal rule */
:deep(.prose hr) {
  border: none;
  border-top: 1px solid hsl(var(--border));
  margin: 1.5rem 0;
}
</style>
