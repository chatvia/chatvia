<script setup lang="ts">
/**
 * Detail sheet for one row of Workspace → Integrations → Connectors: a header
 * with the connector's logo, name and status, then Overview / Tools / Agents
 * tabs. The destructive action is labelled "Disconnect" and deletes the
 * workspace's connection; its confirmation sits outside the Sheet so it can
 * open after the sheet has closed.
 *
 * The tab state (tool catalog, agent list) lives here rather than in the tab
 * components: the Sheet unmounts its content when it closes, and reopening the
 * same connector should not lose what was already loaded.
 */
import { disconnectConnector, toggleConnector } from '@/api/connectors';
import AgentAttachmentList from '@/components/common/AgentAttachmentList.vue';
import DeleteConfirmationDialog from '@/components/common/DeleteConfirmationDialog.vue';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAgentAttachments } from '@/composables/useAgentAttachments';
import { useConnectorText } from '@/composables/useConnectorText';
import { useConnectorTools } from '@/composables/useConnectorTools';
import { connectorLogoUrl } from '@/lib/connectorLogo';
import type { AgentMcpServer, Connector, ConnectorRow } from '@/types/connector';
import { CheckCircle2, Plug } from 'lucide-vue-next';
import { computed, ref, toRef, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';
import ConnectorOverviewTab from './ConnectorOverviewTab.vue';
import ConnectorToolsTab from './ConnectorToolsTab.vue';

const props = defineProps<{
    open: boolean;
    row: Pick<ConnectorRow, 'connector' | 'server'> | null;
}>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    updated: [server: AgentMcpServer];
    removed: [slug: string];
    'connect-token': [connector: Connector];
    'connect-oauth': [connector: Connector];
    'agents-changed': [];
}>();

const { t } = useI18n();
const { connectorName, connectorDescription } = useConnectorText();

const activeTab = ref<'overview' | 'tools' | 'agents'>('overview');
const disconnectOpen = ref(false);

const connector = computed(() => props.row?.connector ?? null);
const server = computed(() => props.row?.server ?? null);
const displayName = computed(() => (connector.value ? connectorName(connector.value) : (server.value?.name ?? '')));
const description = computed(() => (connector.value ? connectorDescription(connector.value) : t('shared.connector_sheet.default_description')));
const isConnected = computed(() => server.value !== null);
const brandColor = computed(() => connector.value?.brandColor ?? '6B7280');
const logo = computed(() => (connector.value ? connectorLogoUrl(connector.value) : null));
const statusLabel = computed(() =>
    isConnected.value ? t('shared.connector_sheet.status_connected') : t('shared.connector_sheet.status_not_connected'),
);
const disconnectTitle = computed(() => t('shared.connector_sheet.disconnect_title', { name: displayName.value }));

const tools = useConnectorTools({
    open: toRef(props, 'open'),
    active: computed(() => activeTab.value === 'tools'),
    server,
    displayName,
    onUpdated: (updated) => emit('updated', updated),
});

const agentMgmt = useAgentAttachments('connector');
const agentsLoaded = ref(false);

// A different connector starts on Overview with a fresh agent list.
watch(
    () => props.row?.server?.slug,
    () => {
        activeTab.value = 'overview';
        agentMgmt.reset();
        agentsLoaded.value = false;
    },
);

// Lazy-load the agent list the first time the user opens the Agents tab.
watch(
    () => [props.open, props.row?.server?.slug, activeTab.value],
    async () => {
        if (!props.open || !server.value) return;
        if (activeTab.value !== 'agents') return;
        if (agentsLoaded.value) return;
        agentsLoaded.value = true;
        await agentMgmt.load(server.value.slug);
    },
);

async function onToggleAttachment(agentId: string): Promise<void> {
    if (!server.value) return;
    await agentMgmt.toggleAttachment(server.value.slug, agentId);
    emit('agents-changed');
}

function startConnect(): void {
    if (!connector.value) return;
    if (connector.value.authStrategy === 'oauth') {
        emit('connect-oauth', connector.value);
    } else {
        emit('connect-token', connector.value);
    }
}

async function toggleEnabled(): Promise<void> {
    if (!server.value) return;
    try {
        emit('updated', (await toggleConnector(server.value.slug)).data);
    } catch (err) {
        console.error(err);
        toast.error(t('shared.connector_sheet.toast_toggle_failed'));
    }
}

function askDisconnect(): void {
    // Sheet + AlertDialog at the same z-index causes overlap glitches; close
    // the sheet first, then open the confirm a beat later.
    emit('update:open', false);
    setTimeout(() => {
        disconnectOpen.value = true;
    }, 150);
}

async function confirmDisconnect(): Promise<void> {
    if (!server.value) return;
    const slug = server.value.slug;
    try {
        await disconnectConnector(slug);
        emit('removed', slug);
        emit('update:open', false);
        toast.success(t('shared.connector_sheet.toast_disconnected', { name: displayName.value }));
    } catch (err) {
        console.error(err);
        toast.error(t('shared.connector_sheet.toast_disconnect_failed'));
    } finally {
        disconnectOpen.value = false;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="(v) => emit('update:open', v)">
        <SheetContent v-if="row" class="w-full overflow-y-auto p-0 sm:max-w-md">
            <SheetHeader class="space-y-5 border-b px-6 pt-7 pb-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg" :style="{ backgroundColor: '#' + brandColor + '15' }">
                        <img v-if="logo" :src="logo" :alt="displayName" class="h-7 w-7" />
                        <Plug v-else class="text-muted-foreground h-6 w-6" />
                    </div>
                    <div class="min-w-0 flex-1 pt-1">
                        <SheetTitle class="truncate text-lg">{{ displayName }}</SheetTitle>
                        <div class="mt-1.5 flex items-center gap-1.5 text-sm">
                            <CheckCircle2 v-if="isConnected" class="h-4 w-4 text-emerald-600" />
                            <span class="text-muted-foreground">{{ statusLabel }}</span>
                        </div>
                    </div>
                </div>
                <SheetDescription class="text-sm leading-relaxed">{{ description }}</SheetDescription>
            </SheetHeader>

            <Tabs v-model="activeTab" class="px-6 pt-5">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="overview">{{ t('shared.connector_sheet.tab_overview') }}</TabsTrigger>
                    <TabsTrigger value="tools" :disabled="!isConnected">{{ t('shared.connector_sheet.tab_tools') }}</TabsTrigger>
                    <TabsTrigger value="agents" :disabled="!isConnected">{{ t('shared.connector_sheet.tab_agents') }}</TabsTrigger>
                </TabsList>

                <ConnectorOverviewTab
                    :connector="connector"
                    :server="server"
                    @connect="startConnect"
                    @toggle-enabled="toggleEnabled"
                    @disconnect="askDisconnect"
                />

                <ConnectorToolsTab
                    v-model:search="tools.search.value"
                    :tools="tools.tools.value"
                    :match-count="tools.filteredTools.value.length"
                    :sections="tools.sections.value"
                    :sections-open="tools.sectionsOpen.value"
                    :expanded="tools.expanded.value"
                    :loading="tools.loading.value"
                    :refreshing="tools.refreshing.value"
                    @refresh="tools.refresh"
                    @section-open="tools.setSectionOpen"
                    @toggle-details="tools.toggleDetails"
                    @set-permission="tools.setToolPermission"
                    @apply-section="tools.applySectionPermission"
                />

                <TabsContent value="agents" class="pt-6">
                    <AgentAttachmentList
                        :intro="t('shared.connector_sheet.agents_toggle_description')"
                        :loading-text="t('shared.connector_sheet.loading_agents')"
                        :empty-text="t('shared.connector_sheet.no_agents_yet')"
                        :agents="agentMgmt.all.value"
                        :loading="agentMgmt.loading.value"
                        :busy-id="agentMgmt.busyId.value"
                        @toggle="onToggleAttachment"
                    />
                </TabsContent>
            </Tabs>

            <div class="h-6"></div>
        </SheetContent>
    </Sheet>

    <DeleteConfirmationDialog
        :open="disconnectOpen"
        :title="disconnectTitle"
        :description="t('shared.connector_sheet.disconnect_description_settings')"
        :confirm-text="t('shared.connector_sheet.disconnect')"
        :cancel-text="t('shared.connector_sheet.keep')"
        @cancel="disconnectOpen = false"
        @confirm="confirmDisconnect"
    />
</template>
