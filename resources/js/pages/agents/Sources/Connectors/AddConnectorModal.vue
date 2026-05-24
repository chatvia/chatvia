<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgentMcpServer } from '@/types/connector';
import axios from 'axios';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
    agentId: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    added: [server: AgentMcpServer];
}>();

type Strategy = 'oauth' | 'token' | 'none';

const name = ref('');
const url = ref('');
const strategy = ref<Strategy>('oauth');
const token = ref('');
const saving = ref(false);
const error = ref<string | null>(null);

const canSubmit = computed(() => {
    if (!name.value.trim() || !url.value.trim()) return false;
    if (strategy.value === 'token' && !token.value.trim()) return false;
    return true;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            name.value = '';
            url.value = '';
            strategy.value = 'oauth';
            token.value = '';
            error.value = null;
        }
    },
);

async function submit(): Promise<void> {
    if (!canSubmit.value || saving.value) return;
    error.value = null;
    saving.value = true;

    try {
        const response = await axios.post(route('agents.sources.connectors.custom.add', props.agentId), {
            name: name.value.trim(),
            url: url.value.trim(),
            auth_strategy: strategy.value,
            token: strategy.value === 'token' ? token.value : null,
        });

        if (response.data.redirect_url) {
            // OAuth: full-page redirect to the upstream authorize endpoint.
            window.location.href = response.data.redirect_url;
            return;
        }

        toast.success(`Added ${name.value}`);
        emit('added', response.data.data);
        emit('update:open', false);
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        error.value =
            err.response?.data?.message ??
            Object.values(err.response?.data?.errors ?? {}).flat()[0] ??
            'Could not add this connector — check the URL and try again.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add custom connector</DialogTitle>
                <DialogDescription>
                    Point the chat at any MCP server. Modern servers (Notion, Linear, Atlassian, …) advertise OAuth — pick that and we'll handle
                    sign-in. Older servers usually take a bearer token.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="conn-name">Display name</Label>
                    <Input id="conn-name" v-model="name" placeholder="e.g. Notion (work)" :disabled="saving" required />
                </div>

                <div class="space-y-1.5">
                    <Label for="conn-url">MCP server URL</Label>
                    <Input id="conn-url" v-model="url" type="url" placeholder="https://mcp.example.com" :disabled="saving" required />
                </div>

                <div class="space-y-2">
                    <Label>Authentication</Label>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="opt in ['oauth', 'token', 'none'] as Strategy[]"
                            :key="opt"
                            class="flex cursor-pointer items-center justify-center rounded-md border p-2 text-sm capitalize"
                            :class="
                                strategy === opt ? 'border-primary bg-primary/5 text-primary' : 'hover:border-foreground/30 text-muted-foreground'
                            "
                        >
                            <input v-model="strategy" type="radio" :value="opt" class="sr-only" />
                            {{ opt === 'oauth' ? 'OAuth (auto)' : opt === 'token' ? 'Bearer token' : 'None' }}
                        </label>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        <span v-if="strategy === 'oauth'">We'll discover the auth server and run sign-in for you.</span>
                        <span v-else-if="strategy === 'token'">We'll send your token as a Bearer header on every call.</span>
                        <span v-else>The server is reachable without auth.</span>
                    </p>
                </div>

                <div v-if="strategy === 'token'" class="space-y-1.5">
                    <Label for="conn-token">Bearer token</Label>
                    <Input id="conn-token" v-model="token" type="password" autocomplete="off" :disabled="saving" />
                </div>

                <p v-if="error" class="text-destructive border-destructive/40 bg-destructive/5 rounded-md border p-2 text-sm">
                    {{ error }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)"> Cancel </Button>
                    <Button type="submit" :disabled="saving || !canSubmit">
                        <LoaderCircle v-if="saving" class="mr-1 h-4 w-4 animate-spin" />
                        {{ strategy === 'oauth' ? 'Continue to sign-in' : 'Add connector' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
