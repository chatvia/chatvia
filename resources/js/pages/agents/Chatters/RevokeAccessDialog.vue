<script setup lang="ts">
// Confirms revoking a chatter's access to this agent. The chatter stays in the
// workspace; on success the parent flips the row to "no access".
import type { AgentChatterRow } from '@/api/chatters';
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
import { destroy as destroyAgentChatter } from '@/routes/agents/chatters';
import { router } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    /**
     * The chatter to revoke. Kept apart from `open` on purpose: the dialog
     * closes itself before the confirm handler runs, and the handler still
     * needs to know who it is for.
     */
    member: AgentChatterRow | null;
}>();

const emit = defineEmits<{
    revoked: [member: AgentChatterRow];
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const isDetaching = ref(false);

const confirmDetach = () => {
    if (!props.member) return;
    const target = props.member;
    isDetaching.value = true;
    router.delete(
        destroyAgentChatter.url({
            agent: props.agentId,
            chatter: target.external_id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('revoked', target);
                toast.success(t('agents.chatters.access_revoked', { name: target.name }));
                open.value = false;
            },
            onError: () => toast.error(t('agents.chatters.revoke_error')),
            onFinish: () => {
                isDetaching.value = false;
            },
        },
    );
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.chatters.revoke_dialog_title') }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('agents.chatters.revoke_dialog_description', { name: member?.name }) }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="isDetaching">{{ t('common.cancel') }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    :disabled="isDetaching"
                    @click="confirmDetach"
                >
                    <LoaderCircle v-if="isDetaching" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('agents.chatters.revoke') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
