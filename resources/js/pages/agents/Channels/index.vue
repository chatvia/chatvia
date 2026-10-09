<script setup lang="ts">
// Per-agent Channels page: a grid of messaging apps (Telegram, Slack,
// Messenger, WhatsApp, Microsoft Teams, Instagram); each opens a slide-over to
// connect, inspect or disconnect that channel. Each channel component owns its
// connect form — the sheet unmounts its content on close, so every open starts
// with a fresh, error-free form.
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import ChannelGrid from './ChannelGrid.vue';
import ChannelSheet from './ChannelSheet.vue';
import DisconnectChannelDialog from './DisconnectChannelDialog.vue';
import InstagramChannel from './InstagramChannel.vue';
import MessengerChannel from './MessengerChannel.vue';
import MsTeamsChannel from './MsTeamsChannel.vue';
import SlackChannel from './SlackChannel.vue';
import TelegramChannel from './TelegramChannel.vue';
import TelegramCommandsDialog from './TelegramCommandsDialog.vue';
import type { ChannelItem, ChannelKind, ChannelsPageProps } from './types';
import { useAutoOpenChannel } from './useAutoOpenChannel';
import { useChannelItems } from './useChannelItems';
import { useWhatsappEmbeddedSignup } from './useWhatsappEmbeddedSignup';
import WhatsappChannel from './WhatsappChannel.vue';

const props = defineProps<ChannelsPageProps>();

const { t } = useI18n();

const { hasPermission } = useTeamPermissions();
const canManage = computed(() => hasPermission('agent.setup'));

const items = useChannelItems(props);

// ── Config sidebar -----------------------------------------------------------
// `selectedKind` (not the object) so the open sheet always reflects fresh props
// after a connect/disconnect reload.
const sheetOpen = ref(false);
const selectedKind = ref<ChannelKind | null>(null);
const selected = computed(() => items.value.find((i) => i.kind === selectedKind.value) ?? null);

const {
    connecting: whatsappConnecting,
    connect: connectWhatsapp,
    preload: preloadWhatsapp,
} = useWhatsappEmbeddedSignup({
    agentId: () => props.agent.external_id,
    whatsapp: () => props.whatsapp,
    sheetOpen,
});

const openChannel = (item: ChannelItem) => {
    selectedKind.value = item.kind;

    if (item.kind === 'whatsapp') {
        preloadWhatsapp();
    }

    sheetOpen.value = true;
};

useAutoOpenChannel(() => props.open, items, openChannel);

// ── Shared dialogs -------------------------------------------------------------
const disconnectOpen = ref(false);
const commandsOpen = ref(false);
</script>

<template>
    <AgentLayout :agent="props.agent" :title="t('agents.channels.title')" :description="t('agents.channels.description')">
        <Head :title="t('agents.channels.head_title', { name: props.agent.name })" />

        <ChannelGrid :items="items" @open="openChannel" />

        <!-- Config sidebar -->
        <ChannelSheet v-model:open="sheetOpen" :channel="selected">
            <TelegramChannel
                v-if="selected?.kind === 'telegram'"
                :agent="props.agent"
                :telegram="props.telegram"
                :can-manage="canManage"
                @disconnect="disconnectOpen = true"
                @manage-commands="commandsOpen = true"
            />
            <SlackChannel
                v-else-if="selected?.kind === 'slack'"
                :agent="props.agent"
                :slack="props.slack"
                :can-manage="canManage"
                @disconnect="disconnectOpen = true"
            />
            <MessengerChannel
                v-else-if="selected?.kind === 'messenger'"
                :agent="props.agent"
                :messenger="props.messenger"
                :can-manage="canManage"
                @disconnect="disconnectOpen = true"
            />
            <WhatsappChannel
                v-else-if="selected?.kind === 'whatsapp'"
                :agent="props.agent"
                :whatsapp="props.whatsapp"
                :can-manage="canManage"
                :connecting="whatsappConnecting"
                @disconnect="disconnectOpen = true"
                @connect="connectWhatsapp"
            />
            <MsTeamsChannel
                v-else-if="selected?.kind === 'msteams'"
                :agent="props.agent"
                :msteams="props.msteams"
                :can-manage="canManage"
                @disconnect="disconnectOpen = true"
            />
            <InstagramChannel
                v-else-if="selected?.kind === 'instagram'"
                :agent="props.agent"
                :instagram="props.instagram"
                :can-manage="canManage"
                @disconnect="disconnectOpen = true"
            />
        </ChannelSheet>

        <DisconnectChannelDialog v-model:open="disconnectOpen" :agent-id="props.agent.external_id" :channel="selected" />

        <TelegramCommandsDialog v-model:open="commandsOpen" :agent-id="props.agent.external_id" :commands="props.telegram.commands" />
    </AgentLayout>
</template>
