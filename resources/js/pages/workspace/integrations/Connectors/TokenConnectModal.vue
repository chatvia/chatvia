<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgentMcpServer, Connector } from '@/types/connector';
import axios from 'axios';
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

const token = ref('');
const serverUrl = ref('');
const saving = ref(false);
const error = ref<string | null>(null);

const requiresUrl = computed(() => props.connector?.requiresUrl ?? false);

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
        const payload: Record<string, string> = { token: token.value };
        if (requiresUrl.value) payload.url = serverUrl.value;
        const response = await axios.post(route('integrations.connectors.connect', { slug: props.connector.slug }), payload);
        emit('connected', response.data.data, response.data.tools_count);
        emit('update:open', false);
        toast.success(t('workspace.connectors.toast.connected', { name: props.connector.name }));
    } catch (e: unknown) {
        const axiosErr = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const errors = axiosErr.response?.data?.errors;
        error.value = axiosErr.response?.data?.message ?? errors?.url?.[0] ?? errors?.token?.[0] ?? t('workspace.connectors.token_modal.error');
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
                        <img
                            :src="connector.logoUrl ?? `https://cdn.simpleicons.org/${connector.icon}/${connector.brandColor}`"
                            :alt="connector.name"
                            class="h-5 w-5"
                        />
                    </div>
                    <DialogTitle>{{ t('workspace.connectors.connect_title', { name: connector.name }) }}</DialogTitle>
                </div>
                <DialogDescription>
                    {{ connector.description }}
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
                        {{ t('workspace.connectors.token_modal.server_url_hint', { name: connector.name }) }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <Label for="token">{{ connector.tokenLabel ?? t('workspace.connectors.token_modal.token') }}</Label>
                    <Input
                        id="token"
                        v-model="token"
                        type="password"
                        autocomplete="off"
                        :placeholder="connector.tokenPlaceholder ?? ''"
                        :disabled="saving"
                        required
                    />
                    <p v-if="connector.tokenHelpUrl" class="text-muted-foreground flex items-center gap-1 text-xs">
                        <a :href="connector.tokenHelpUrl" target="_blank" class="hover:underline">{{ t('workspace.connectors.token_modal.help_link') }}</a>
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
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)">{{ t('workspace.common.cancel') }}</Button>
                    <Button type="submit" :disabled="saving || !token || (requiresUrl && !serverUrl)">
                        <LoaderCircle v-if="saving" class="me-1 h-4 w-4 animate-spin" />
                        {{ t('workspace.common.connect') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
