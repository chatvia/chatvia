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
import { disconnect as disconnectInstagram } from '@/routes/agents/channels/instagram';
import { disconnect as disconnectMessenger } from '@/routes/agents/channels/messenger';
import { disconnect as disconnectMsTeams } from '@/routes/agents/channels/msteams';
import { disconnect as disconnectSlack } from '@/routes/agents/channels/slack';
import { disconnect as disconnectTelegram } from '@/routes/agents/channels/telegram';
import { disconnect as disconnectWhatsapp } from '@/routes/agents/channels/whatsapp';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import type { ChannelItem, ChannelKind } from './types';

const props = defineProps<{
    agentId: string;
    channel: ChannelItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

// Typed per kind so adding a channel without its disconnect route fails to compile.
const disconnectUrl: Record<ChannelKind, (agent: string) => string> = {
    telegram: (agent) => disconnectTelegram.url(agent),
    slack: (agent) => disconnectSlack.url(agent),
    whatsapp: (agent) => disconnectWhatsapp.url(agent),
    messenger: (agent) => disconnectMessenger.url(agent),
    msteams: (agent) => disconnectMsTeams.url(agent),
    instagram: (agent) => disconnectInstagram.url(agent),
};

const disconnect = () => {
    const channel = props.channel;
    if (channel === null) return;

    router.delete(disconnectUrl[channel.kind](props.agentId), {
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
