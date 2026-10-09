<script setup lang="ts">
// Confirmation before disconnecting the channel open in the slide-over. The
// target comes from the `channel` prop (the selected channel), not from the
// open state, because reka's AlertDialogAction closes the dialog before its
// click handler runs.
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
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { ChannelItem } from './types';

const props = defineProps<{
    agentId: string;
    channel: ChannelItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const disconnect = () => {
    const channel = props.channel;
    if (channel === null) return;

    router.delete(route(`agents.channels.${channel.kind}.disconnect`, props.agentId), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            open.value = false;
            toast.success(t('agents.channels.disconnected_toast', { name: channel.name }));
        },
    });
};
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ t('agents.channels.disconnect_dialog.title', { name: channel?.name }) }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ t('agents.channels.disconnect_dialog.description', { name: channel?.name }) }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>{{ t('agents.channels.disconnect_dialog.cancel') }}</AlertDialogCancel>
                <AlertDialogAction class="bg-destructive text-destructive-foreground hover:bg-destructive/90" @click="disconnect">
                    {{ t('agents.channels.disconnect') }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
