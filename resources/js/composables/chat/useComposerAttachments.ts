/**
 * Files attached in the chat composer before the message is sent: upload,
 * optimistic remove, and the per-chat limit. A send must wait for in-flight
 * removals, because the server attaches every unsent file of the conversation
 * to the next message regardless of what the client lists.
 */
import { deleteAttachment, uploadAttachment } from '@/api/chat';
import { errorMessage, validationErrors } from '@/api/http';
import type { ChatAttachment } from '@/types/chat';
import { computed, ref, type Ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface ComposerAttachmentOptions {
    /** POST target for uploads; uploads are off without it. */
    uploadEndpoint: Ref<string | undefined>;
    uploadHeaders: Ref<Record<string, string> | undefined>;
    /** DELETE url builder; without it a remove only clears local state. */
    deleteEndpoint: Ref<((id: string) => string) | undefined>;
    maxFiles: Ref<number>;
    onUploaded: (attachment: ChatAttachment) => void;
}

export function useComposerAttachments(options: ComposerAttachmentOptions) {
    const { t } = useI18n();

    const attachments = ref<ChatAttachment[]>([]);
    const isUploading = ref(false);
    const pendingDeleteCount = ref(0);

    // The server enforces the same limit; checking here saves a round trip.
    // Only this turn's files are counted, so a conversation that already has
    // files can still get a 422 from the server (shown as a toast).
    const canUploadMore = computed(() => attachments.value.length < options.maxFiles.value);
    const isBusy = computed(() => isUploading.value || pendingDeleteCount.value > 0);

    const limitMessage = () => t('composer.upload_limit_reached', { count: options.maxFiles.value }, { plural: options.maxFiles.value });

    /** False (with a toast) when the limit is already reached. */
    function ensureRoom(): boolean {
        if (canUploadMore.value) return true;
        toast.error(limitMessage());

        return false;
    }

    async function upload(file: File) {
        const endpoint = options.uploadEndpoint.value;
        if (!endpoint || !ensureRoom()) return;

        isUploading.value = true;
        try {
            const attachment = await uploadAttachment(endpoint, file, options.uploadHeaders.value ?? {});
            attachments.value.push(attachment);
            options.onUploaded(attachment);
        } catch (error) {
            toast.error(validationErrors(error).file || errorMessage(error, t('composer.upload_failed')));
        } finally {
            isUploading.value = false;
        }
    }

    /**
     * Drops the chip at once and restores it if the server delete fails;
     * leaving it removed locally would still send the file to the model.
     */
    async function remove(id: string) {
        const index = attachments.value.findIndex((attachment) => attachment.id === id);
        if (index === -1) return;

        const removed = attachments.value[index];
        attachments.value = attachments.value.filter((attachment) => attachment.id !== id);

        const buildUrl = options.deleteEndpoint.value;
        if (!buildUrl) return;

        pendingDeleteCount.value++;
        try {
            await deleteAttachment(buildUrl(id));
        } catch (error) {
            attachments.value.splice(index, 0, removed);
            toast.error(errorMessage(error, t('composer.delete_failed')));
        } finally {
            pendingDeleteCount.value--;
        }
    }

    /** Hands over the attached files for sending and clears the list. */
    function takeAll(): ChatAttachment[] {
        const taken = [...attachments.value];
        attachments.value = [];

        return taken;
    }

    return { attachments, isUploading, isBusy, canUploadMore, limitMessage, ensureRoom, upload, remove, takeAll };
}
