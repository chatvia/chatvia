<script lang="ts" setup>
/**
 * Connect Salesforce with the workspace's own OAuth client (Salesforce has no
 * shared app): collects the consumer key/secret, environment, MCP server and
 * optional My Domain, then hands the browser to Salesforce's consent screen.
 */
import { connectSalesforce, type SalesforceConnectPayload } from '@/api/connectors';
import { errorMessage, validationErrors } from '@/api/http';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Connector } from '@/types/connector';
import { ExternalLink, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    open: boolean;
    connector: Connector | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const { t } = useI18n();

const SERVERS = computed(() => [
    { value: 'sobject-all', label: t('workspace.connectors.salesforce.servers.sobject_all') },
    { value: 'sobject-reads', label: t('workspace.connectors.salesforce.servers.sobject_reads') },
    { value: 'flows', label: t('workspace.connectors.salesforce.servers.flows') },
    { value: 'invocable-actions', label: t('workspace.connectors.salesforce.servers.invocable_actions') },
    { value: 'data-360', label: t('workspace.connectors.salesforce.servers.data_360') },
    { value: 'prompt-builder', label: t('workspace.connectors.salesforce.servers.prompt_builder') },
    { value: 'tableau-next', label: t('workspace.connectors.salesforce.servers.tableau_next') },
]);

const clientId = ref('');
const clientSecret = ref('');
const environment = ref<'production' | 'sandbox'>('production');
const myDomain = ref('');
const server = ref('sobject-all');
const saving = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            clientId.value = '';
            clientSecret.value = '';
            environment.value = 'production';
            myDomain.value = '';
            server.value = 'sobject-all';
            error.value = null;
        }
    },
);

async function submit(): Promise<void> {
    if (!props.connector || saving.value || !clientId.value.trim()) return;
    error.value = null;
    saving.value = true;

    try {
        const payload: SalesforceConnectPayload = {
            client_id: clientId.value.trim(),
            environment: environment.value,
            server: server.value,
        };
        if (clientSecret.value.trim()) payload.client_secret = clientSecret.value.trim();
        if (myDomain.value.trim()) payload.my_domain = myDomain.value.trim();

        const response = await connectSalesforce(payload);
        // Full-page navigation to Salesforce's consent screen.
        window.location.href = response.redirect_url;
    } catch (err) {
        const fieldErrors = validationErrors(err);
        error.value = errorMessage(err, fieldErrors.client_id ?? fieldErrors.my_domain ?? t('workspace.connectors.salesforce.error'));
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
                        <img :src="connector.logoUrl ?? ''" :alt="connector.name" class="h-5 w-5" />
                    </div>
                    <DialogTitle>{{ t('workspace.connectors.connect_title', { name: connector.name }) }}</DialogTitle>
                </div>
                <DialogDescription>
                    {{ t('workspace.connectors.salesforce.description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submit">
                <!-- Environment -->
                <div class="space-y-1.5">
                    <Label>{{ t('workspace.connectors.salesforce.environment') }}</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            :variant="environment === 'production' ? 'default' : 'outline'"
                            :disabled="saving"
                            @click="environment = 'production'"
                        >
                            {{ t('workspace.connectors.salesforce.production') }}
                        </Button>
                        <Button
                            type="button"
                            :variant="environment === 'sandbox' ? 'default' : 'outline'"
                            :disabled="saving"
                            @click="environment = 'sandbox'"
                        >
                            {{ t('workspace.connectors.salesforce.sandbox') }}
                        </Button>
                    </div>
                </div>

                <!-- Consumer Key -->
                <div class="space-y-1.5">
                    <Label for="sf-client-id">{{ t('workspace.connectors.salesforce.consumer_key') }}</Label>
                    <Input
                        id="sf-client-id"
                        v-model="clientId"
                        autocomplete="off"
                        placeholder="3MVG9..."
                        :disabled="saving"
                        required
                        class="font-mono"
                    />
                    <p v-if="connector.tokenHelpUrl" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <a :href="connector.tokenHelpUrl" target="_blank" class="hover:underline">{{
                            t('workspace.connectors.salesforce.help_link')
                        }}</a>
                        <ExternalLink class="h-3 w-3" />
                    </p>
                </div>

                <!-- Consumer Secret (optional) -->
                <div class="space-y-1.5">
                    <Label for="sf-client-secret"
                        >{{ t('workspace.connectors.salesforce.consumer_secret') }}
                        <span class="text-muted-foreground font-normal">{{ t('workspace.connectors.salesforce.optional') }}</span></Label
                    >
                    <Input
                        id="sf-client-secret"
                        v-model="clientSecret"
                        type="password"
                        autocomplete="off"
                        :placeholder="t('workspace.connectors.salesforce.consumer_secret_placeholder')"
                        :disabled="saving"
                    />
                </div>

                <!-- Server -->
                <div class="space-y-1.5">
                    <Label for="sf-server">{{ t('workspace.connectors.salesforce.mcp_server') }}</Label>
                    <select
                        id="sf-server"
                        v-model="server"
                        :disabled="saving"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <option v-for="s in SERVERS" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>

                <!-- My Domain (optional) -->
                <div class="space-y-1.5">
                    <Label for="sf-my-domain"
                        >{{ t('workspace.connectors.salesforce.my_domain') }}
                        <span class="text-muted-foreground font-normal">{{ t('workspace.connectors.salesforce.optional') }}</span></Label
                    >
                    <Input
                        id="sf-my-domain"
                        v-model="myDomain"
                        type="url"
                        autocomplete="off"
                        placeholder="https://acme.my.salesforce.com"
                        :disabled="saving"
                    />
                    <p class="text-muted-foreground text-xs">
                        {{ t('workspace.connectors.salesforce.my_domain_hint') }}
                    </p>
                </div>

                <p v-if="error" class="text-destructive border-destructive/40 bg-destructive/5 rounded-md border p-2 text-sm">
                    {{ error }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)">{{
                        t('workspace.common.cancel')
                    }}</Button>
                    <Button type="submit" :disabled="saving || !clientId.trim()">
                        <LoaderCircle v-if="saving" class="me-1 h-4 w-4 animate-spin" />
                        {{ t('workspace.connectors.salesforce.continue') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
