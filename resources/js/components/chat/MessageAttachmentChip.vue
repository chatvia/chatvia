<script setup lang="ts">
/**
 * A file attached to a sent prompt: icon, name and size, linking to the file.
 * Hovering reveals a remove button that first warns the assistant will lose
 * access to the file.
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
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { formatFileSize } from '@/lib/fileSize';
import type { ChatAttachment } from '@/types/chat';
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    attachment: ChatAttachment;
}>();

const emit = defineEmits<{
    remove: [attachmentId: string];
}>();

const { t } = useI18n();

// Icons live in public/images/icons/files/{ext}.svg; add an SVG there and the
// chip picks it up. Anything without one gets the generic file icon.
const KNOWN_EXTENSIONS = new Set(['pdf']);

const iconUrl = computed(() => {
    const extension = props.attachment.filename.split('.').pop()?.toLowerCase();
    if (extension && KNOWN_EXTENSIONS.has(extension)) return `/images/icons/files/${extension}.svg`;
    if (props.attachment.mime_type === 'application/pdf') return '/images/icons/files/pdf.svg';

    return '/images/icons/files/file.svg';
});
</script>

<template>
    <div class="group/chip relative">
        <a
            :href="attachment.url"
            target="_blank"
            rel="noopener noreferrer"
            class="bg-card text-foreground hover:bg-muted/50 inline-flex max-w-[260px] items-center gap-2 rounded-lg border px-3 py-2 text-xs transition-colors"
        >
            <img :src="iconUrl" :alt="attachment.mime_type" class="h-6 w-6 shrink-0" />
            <div class="flex min-w-0 flex-col items-start leading-tight">
                <span class="max-w-[180px] truncate font-medium">{{ attachment.filename }}</span>
                <span class="text-muted-foreground text-[11px]">{{ formatFileSize(attachment.size_bytes) }}</span>
            </div>
        </a>
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <button
                    type="button"
                    class="bg-card text-muted-foreground absolute -end-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full border opacity-0 shadow-sm transition-opacity group-hover/chip:opacity-100 hover:bg-red-500/10 hover:text-red-600 focus:opacity-100"
                    :aria-label="t('messages.delete_attachment_aria')"
                >
                    <X class="h-3 w-3" />
                </button>
            </AlertDialogTrigger>
            <AlertDialogContent class="rounded-2xl">
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('messages.delete_attachment_title') }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('messages.delete_attachment_description') }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel class="rounded-lg">{{ t('common.cancel') }}</AlertDialogCancel>
                    <AlertDialogAction @click="emit('remove', attachment.id)" class="rounded-lg bg-red-600 hover:bg-red-700">
                        {{ t('common.delete') }}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
