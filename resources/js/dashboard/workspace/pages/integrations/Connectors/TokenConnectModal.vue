<script lang="ts" setup>
/**
 * Connect a catalog connector that authenticates with an API token. Connectors
 * without a hosted MCP server (`requiresUrl`) also ask for the workspace's own
 * server URL. The token format hint is advisory only; the server validates.
 */
import { connectConnector, type ConnectTokenPayload } from '@/dashboard/workspace/api/connectors';
import { errorMessage, validationErrors } from '@/shared/api/http';
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { useConnectorText } from '@/dashboard/composables/useConnectorText';
import { connectorLogoUrl } from '@/dashboard/lib/connectorLogo';
import type { AgentMcpServer, Connector } from '@/shared/types/connector';
import { ExternalLink, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
    connector: Connector | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    connected: [server: AgentMcpServer, toolsCount: number];
}>();

const { t } = useI18n();
const { connectorName, connectorDescription, connectorTokenLabel, connectorTokenPlaceholder } = useConnectorText();

const token = ref('');
const serverUrl = ref('');
const saving = ref(false);
const error = ref<string | null>(null);

const requiresUrl = computed(() => props.connector?.requiresUrl ?? false);
const tokenLabel = computed(() => (props.connector ? connectorTokenLabel(props.connector) : null));
const tokenPlaceholder = computed(() => (props.connector ? connectorTokenPlaceholder(props.connector) : null));

watch(
    () => props.open,
    (open) => {
        if (open) {
            token.value = '';
            serverUrl.value = '';
            error.value = null;
        }
    },
);

const formatValid = computed(() => {
    if (!props.connector?.tokenPattern || !token.value) return true;
    try {
        return new RegExp(props.connector.tokenPattern).test(token.value);
    } catch {
        return true;
    }
});

async function submit(): Promise<void> {
    if (!props.connector || saving.value) return;
    error.value = null;
    saving.value = true;

    try {
        const payload: ConnectTokenPayload = { token: token.value };
        if (requiresUrl.value) payload.url = serverUrl.value;
        const response = await connectConnector(props.connector.slug, payload);
        emit('connected', response.data, response.tools_count);
        emit('update:open', false);
        toast.success(t('workspace.connectors.toast.connected', { name: connectorName(props.connector) }));
    } catch (err) {
        const fieldErrors = validationErrors(err);
        error.value = errorMessage(err, fieldErrors.url ?? fieldErrors.token ?? t('workspace.connectors.token_modal.error'));
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent v-if="connector" class="sm:max-w-md">
            <DialogHeader>
                <div class="mb-1 flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-md" :style="{ backgroundColor: '#' + connector.brandColor + '15' }">
                        <img :src="connectorLogoUrl(connector)" :alt="connector.name" class="h-5 w-5" />
                    </div>
                    <DialogTitle>{{ t('workspace.connectors.connect_title', { name: connectorName(connector) }) }}</DialogTitle>
                </div>
                <DialogDescription>
                    {{ connectorDescription(connector) }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submit">
                <div v-if="requiresUrl" class="space-y-1.5">
                    <Label for="server-url">{{ t('workspace.connectors.token_modal.server_url') }}</Label>
                    <Input
                        id="server-url"
                        v-model="serverUrl"
                        type="url"
                        autocomplete="off"
                        :placeholder="`https://your-${connector.slug}-mcp.example.com/mcp`"
                        :disabled="saving"
                        required
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ t('workspace.connectors.token_modal.server_url_hint', { name: connectorName(connector) }) }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="token">{{ tokenLabel ?? t('workspace.connectors.token_modal.token') }}</Label>
                    <Input
                        id="token"
                        v-model="token"
                        type="password"
                        autocomplete="off"
                        :placeholder="tokenPlaceholder ?? ''"
                        :disabled="saving"
                        required
                    />
                    <p v-if="connector?.tokenHelpUrl" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <a :href="connector?.tokenHelpUrl" target="_blank" class="hover:underline">{{
                            t('workspace.connectors.token_modal.help_link')
                        }}</a>
                        <ExternalLink class="h-3 w-3" />
                    </p>
                    <p v-if="token && !formatValid" class="text-xs text-amber-600">
                        {{ t('workspace.connectors.token_modal.format_warning') }}
                    </p>
                </div>

                <p v-if="error" class="text-destructive border-destructive/40 bg-destructive/5 rounded-md border p-2 text-sm">
                    {{ error }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)">{{
                        t('workspace.common.cancel')
                    }}</Button>
                    <Button type="submit" :disabled="saving || !token || (requiresUrl && !serverUrl)">
                        <LoaderCircle v-if="saving" class="me-1 h-4 w-4 animate-spin" />
                        {{ t('workspace.common.connect') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
