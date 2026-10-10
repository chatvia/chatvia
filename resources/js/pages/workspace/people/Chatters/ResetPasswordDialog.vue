<script setup lang="ts">
// Confirms emailing a password-reset link to a chatter.
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
import { resetPassword } from '@/routes/people/chatters';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    /**
     * The chatter to reset. Kept apart from `open` on purpose: the dialog
     * closes itself before the confirm handler runs, and the handler still
     * needs to know who it is for.
     */
    member: WorkspaceChatterRow | null;
}>();

const emit = defineEmits<{
    sent: [member: WorkspaceChatterRow];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const isResetting = ref(false);

const confirmReset = () => {
    if (!props.member) return;
    const target = props.member;
    isResetting.value = true;
    router.post(
        resetPassword.url(target.external_id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('workspace.members.toast.reset_sent', { email: target.email }));
                open.value = false;
                emit('sent', target);
            },
            onError: () => toast.error(t('workspace.members.toast.reset_failed')),
            onFinish: () => {
                isResetting.value = false;
            },
        },
    );
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('workspace.members.reset_dialog.title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    <i18n-t keypath="workspace.members.reset_dialog.description" tag="span">
                        <template #email
                            ><span class="font-medium">{{ member?.email }}</span></template
                        >
                    </i18n-t>
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isResetting">{{ t('workspace.common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction :disabled="isResetting" @click="confirmReset">
                    <LoaderCircle v-if="isResetting" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('workspace.members.reset_dialog.confirm') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
