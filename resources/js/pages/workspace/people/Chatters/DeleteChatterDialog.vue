<script setup lang="ts">
// Confirms permanently deleting a chatter (and their access to every agent).
import type { WorkspaceChatterRow } from '@/api/chatters';
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
import { destroy as destroyChatter } from '@/routes/people/chatters';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    /**
     * The chatter to delete. Kept apart from `open` on purpose: the dialog
     * closes itself before the confirm handler runs, and the handler still
     * needs to know who it is for.
     */
    member: WorkspaceChatterRow | null;
}>();

const emit = defineEmits<{
    deleted: [member: WorkspaceChatterRow];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const isDeleting = ref(false);

const confirmDelete = () => {
    if (!props.member) return;
    const target = props.member;
    isDeleting.value = true;
    router.delete(destroyChatter.url(target.external_id), {
        preserveScroll: true,
        onSuccess: () => {
            emit('deleted', target);
            toast.success(t('workspace.members.toast.removed', { name: target.name }));
            open.value = false;
        },
        onError: () => toast.error(t('workspace.members.toast.remove_failed')),
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('workspace.members.delete_dialog.title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    <i18n-t keypath="workspace.members.delete_dialog.description" tag="span">
                        <template #name
                            ><span class="font-medium">{{ member?.name }}</span></template
                        >
                    </i18n-t>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isDeleting">{{ t('workspace.common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="isDeleting"
                    @click="confirmDelete"
                >
                    <LoaderCircle v-if="isDeleting" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('workspace.members.delete_dialog.confirm') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
