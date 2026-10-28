<script setup lang="ts">
/**
 * The files attached in the chat composer, shown above the input row, plus an
 * "Uploading…" chip while a file is on its way.
 */
import { formatFileSize } from '@/chat/lib/fileSize';
import type { ChatAttachment } from '@/shared/types/chat';
import { FileText, Loader2, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    attachments: ChatAttachment[];
    uploading: boolean;
}>();

const emit = defineEmits<{
    remove: [attachmentId: string];
}>();

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-wrap gap-2 px-4 pt-3">
        <div
            v-for="attachment in attachments"
            :key="attachment.id"
            class="bg-muted/70 text-foreground flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs"
        >
            <FileText class="text-muted-foreground h-3.5 w-3.5 shrink-0" />
            <span class="max-w-[180px] truncate font-medium">{{ attachment.filename }}</span>
            <span class="text-muted-foreground">{{ formatFileSize(attachment.size_bytes) }}</span>
            <span v-if="attachment.pages" class="text-muted-foreground">
                · {{ t('composer.pdf_pages', { count: attachment.pages }, { plural: attachment.pages }) }}
            </span>
            <button
                type="button"
                @click="emit('remove', attachment.id)"
                class="text-muted-foreground hover:bg-background hover:text-foreground -me-1 rounded-md p-0.5 transition-colors"
                :aria-label="t('composer.remove_attachment')"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>
        <div v-if="uploading" class="text-muted-foreground bg-muted/70 flex items-center gap-2 rounded-lg border px-2.5 py-1.5 text-xs">
            <Loader2 class="h-3.5 w-3.5 animate-spin" />
            {{ t('composer.uploading') }}
        </div>
    </div>
</template>
