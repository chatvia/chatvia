<script setup lang="ts">
/**
 * Overview tab of the connector detail sheet. Before connecting it offers the
 * connect / sign-in button; once connected it lists the server's details
 * (id, endpoint, auth, usage, last error) and holds the enable switch and the
 * Disconnect button. The requests and the disconnect confirmation live in the
 * sheet, which stays mounted while this tab's DOM comes and goes.
 */
import { Button } from '@/shared/ui/button';
import { TabsContent } from '@/shared/ui/tabs';
import { useDateFormat } from '@/dashboard/composables/useDateFormat';
import type { AgentMcpServer, Connector } from '@/shared/types/connector';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, Plug, Unplug } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    connector: Connector | null;
    server: AgentMcpServer | null;
}>();
const emit = defineEmits<{
    connect: [];
    'toggle-enabled': [];
    disconnect: [];
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
const { copy, copied: idCopied } = useClipboard({ legacy: true, copiedDuring: 2000 });

const isConnected = computed(() => props.server !== null);
const isEnabled = computed(() => props.server?.is_enabled === true);

const connectButtonLabel = computed(() => {
    if (!props.connector) return '';
    return props.connector.authStrategy === 'oauth'
        ? t('shared.connector_sheet.sign_in_with', { name: props.connector.name })
        : t('shared.connector_sheet.connect_name', { name: props.connector.name });
});

async function copyServerId(): Promise<void> {
    if (!props.server) return;
    await copy(props.server.external_id);
}
</script>

<template>
    <TabsContent value="overview" class="space-y-6 pt-6">
        <div v-if="!isConnected && connector">
            <p v-if="connector.comingSoon" class="text-muted-foreground text-sm">{{ t('shared.connector_sheet.coming_soon') }}</p>
            <Button v-else class="w-full" @click="emit('connect')">
                <Plug class="me-2 h-4 w-4" />
                {{ connectButtonLabel }}
            </Button>
        </div>

        <dl v-if="isConnected" class="divide-border divide-y text-sm">
            <div v-if="server" class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.server_id') }}</dt>
                <dd class="col-span-2 flex min-w-0 items-center gap-1.5">
                    <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="server?.external_id">{{ server?.external_id }}</code>
                    <Button
                        variant="ghost"
                        size="sm"
                        class="h-6 shrink-0 px-1.5"
                        :title="t('shared.connector_sheet.copy_server_id_title')"
                        :aria-label="t('shared.connector_sheet.copy_server_id')"
                        @click="copyServerId"
                    >
                        <Check v-if="idCopied" class="size-3.5 text-green-600" />
                        <Copy v-else class="size-3.5" />
                    </Button>
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3 first:pt-0">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.endpoint') }}</dt>
                <dd class="col-span-2 font-mono text-xs leading-relaxed break-all">{{ server?.url }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.auth') }}</dt>
                <dd class="col-span-2 capitalize">{{ server?.auth_type }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.tools_count') }}</dt>
                <dd class="col-span-2">{{ server?.catalog_tools_count }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.calls') }}</dt>
                <dd class="col-span-2">{{ server?.invocation_count ?? 0 }}</dd>
            </div>
            <div v-if="server?.last_invoked_at" class="grid grid-cols-3 gap-4 py-3">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.last_call') }}</dt>
                <dd class="col-span-2">{{ formatDateTime(server.last_invoked_at) }}</dd>
            </div>
            <div v-if="server?.last_error" class="grid grid-cols-3 gap-4 py-3 last:pb-0">
                <dt class="text-muted-foreground col-span-1">{{ t('shared.connector_sheet.last_error') }}</dt>
                <dd class="text-destructive col-span-2">{{ server.last_error }}</dd>
            </div>
        </dl>

        <div v-if="isConnected" class="space-y-4 border-t pt-5">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium">{{ t('shared.connector_sheet.enabled') }}</span>
                <Button size="sm" :variant="isEnabled ? 'default' : 'outline'" class="min-w-16" @click="emit('toggle-enabled')">
                    {{ isEnabled ? t('shared.connector_sheet.on') : t('shared.connector_sheet.off') }}
                </Button>
            </div>
            <Button variant="destructive" class="w-full" @click="emit('disconnect')">
                <Unplug class="me-2 h-4 w-4" />
                {{ t('shared.connector_sheet.disconnect') }}
            </Button>
        </div>
    </TabsContent>
</template>
