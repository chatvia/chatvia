<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Connector } from '@/types/connector';
import axios from 'axios';
import { ExternalLink, LoaderCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    open: boolean;
    connector: Connector | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const SERVERS = [
    { value: 'sobject-all', label: 'Objects — read & write (recommended)' },
    { value: 'sobject-reads', label: 'Objects — read only' },
    { value: 'flows', label: 'Flows' },
    { value: 'invocable-actions', label: 'Invocable actions' },
    { value: 'data-360', label: 'Data 360' },
    { value: 'prompt-builder', label: 'Prompt templates' },
    { value: 'tableau-next', label: 'Tableau Next' },
];

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
        const payload: Record<string, string> = {
            client_id: clientId.value.trim(),
            environment: environment.value,
            server: server.value,
        };
        if (clientSecret.value.trim()) payload.client_secret = clientSecret.value.trim();
        if (myDomain.value.trim()) payload.my_domain = myDomain.value.trim();

        const response = await axios.post(route('settings.connectors.salesforce.connect'), payload);
        // Full-page navigation to Salesforce's consent screen.
        window.location.href = response.data.redirect_url;
    } catch (e: unknown) {
        const axiosErr = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const errors = axiosErr.response?.data?.errors;
        error.value =
            axiosErr.response?.data?.message ??
            errors?.client_id?.[0] ??
            errors?.my_domain?.[0] ??
            'Could not start the connection — please check the details and try again.';
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
                    <DialogTitle>Connect {{ connector.name }}</DialogTitle>
                </div>
                <DialogDescription>
                    Salesforce uses a per-org OAuth app. Create an External Client App in your org, then enter its Consumer Key below — you'll approve
                    access on Salesforce next.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submit">
                <!-- Environment -->
                <div class="space-y-1.5">
                    <Label>Environment</Label>
                    <div class="grid grid-cols-2 gap-2">
                        <Button type="button" :variant="environment === 'production' ? 'default' : 'outline'" :disabled="saving" @click="environment = 'production'">
                            Production
                        </Button>
                        <Button type="button" :variant="environment === 'sandbox' ? 'default' : 'outline'" :disabled="saving" @click="environment = 'sandbox'">
                            Sandbox
                        </Button>
                    </div>
                </div>

                <!-- Consumer Key -->
                <div class="space-y-1.5">
                    <Label for="sf-client-id">Consumer Key</Label>
                    <Input id="sf-client-id" v-model="clientId" autocomplete="off" placeholder="3MVG9..." :disabled="saving" required class="font-mono" />
                    <p v-if="connector.tokenHelpUrl" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <a :href="connector.tokenHelpUrl" target="_blank" class="hover:underline">How to create an External Client App</a>
                        <ExternalLink class="h-3 w-3" />
                    </p>
                </div>

                <!-- Consumer Secret (optional) -->
                <div class="space-y-1.5">
                    <Label for="sf-client-secret">Consumer Secret <span class="text-muted-foreground font-normal">(optional)</span></Label>
                    <Input id="sf-client-secret" v-model="clientSecret" type="password" autocomplete="off" placeholder="Leave blank for a PKCE public app" :disabled="saving" />
                </div>

                <!-- Server -->
                <div class="space-y-1.5">
                    <Label for="sf-server">MCP server</Label>
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
                    <Label for="sf-my-domain">My Domain <span class="text-muted-foreground font-normal">(optional)</span></Label>
                    <Input id="sf-my-domain" v-model="myDomain" type="url" autocomplete="off" placeholder="https://acme.my.salesforce.com" :disabled="saving" />
                    <p class="text-muted-foreground text-xs">Leave blank to sign in via login.salesforce.com. Required if your org enforces My Domain.</p>
                </div>

                <p v-if="error" class="text-destructive border-destructive/40 bg-destructive/5 rounded-md border p-2 text-sm">
                    {{ error }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)">Cancel</Button>
                    <Button type="submit" :disabled="saving || !clientId.trim()">
                        <LoaderCircle v-if="saving" class="mr-1 h-4 w-4 animate-spin" />
                        Continue to Salesforce
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
