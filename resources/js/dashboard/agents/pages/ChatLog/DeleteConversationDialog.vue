<script lang="ts" setup>
/**
 * Confirmation for deleting the selected chat log conversation. Performs the
 * delete and reports success with a toast; the page then leaves the
 * conversation (see the `deleted` event).
 */
import { deleteChatLogConversation } from '@/dashboard/api/chatLog';
import { errorMessage } from '@/shared/api/http';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/shared/ui/alert-dialog';
import { Button } from '@/shared/ui/button';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    conversationId: string | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{
    deleted: [];
}>();

const { t } = useI18n();
const isDeleting = ref(false);

async function deleteConversation() {
    if (!props.conversationId || isDeleting.value) return;

    isDeleting.value = true;
    try {
        await deleteChatLogConversation(props.agentId, props.conversationId);
        toast.success(t('agents.chatlog.delete.success'));
        open.value = false;
        emit('deleted');
    } catch (error) {
        toast.error(t('agents.chatlog.delete.error_title'), {
            description: errorMessage(error, t('agents.chatlog.delete.error_description')),
        });
    } finally {
        isDeleting.value = false;
    }
}
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent class="max-w-md">
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.chatlog.delete.dialog_title') }}</AlertDialogTitle>
                <AlertDialogDescription> {{ t('agents.chatlog.delete.dialog_description') }} </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isDeleting">
                    {{ t('agents.chatlog.delete.cancel') }}
                </AlertDialogCancel>
                <Button variant="destructive" @click="deleteConversation" :disabled="isDeleting">
                    <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                    {{ isDeleting ? t('agents.chatlog.delete.deleting') : t('agents.chatlog.delete.delete') }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
