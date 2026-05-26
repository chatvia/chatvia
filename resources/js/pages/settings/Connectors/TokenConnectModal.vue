<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgentMcpServer, Connector } from '@/types/connector';
import axios from 'axios';
import { ExternalLink, LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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

const token = ref('');
const saving = ref(false);
const error = ref<string | null>(null);

watch(
    () => props.open,
    (open) => {
        if (open) {
            token.value = '';
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
        const response = await axios.post(route('settings.connectors.connect', { slug: props.connector.slug }), {
            token: token.value,
        });
        emit('connected', response.data.data, response.data.tools_count);
        emit('update:open', false);
        toast.success(`Connected ${props.connector.name}`);
    } catch (e: unknown) {
        const axiosErr = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        error.value = axiosErr.response?.data?.message ?? axiosErr.response?.data?.errors?.token?.[0] ?? 'Could not connect — please try again.';
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
                    <DialogTitle>Connect {{ connector.name }}</DialogTitle>
                </div>
                <DialogDescription>
                    {{ connector.description }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="token">{{ connector.tokenLabel ?? 'Token' }}</Label>
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
                        <a :href="connector.tokenHelpUrl" target="_blank" class="hover:underline"> Where do I find this? </a>
                        <ExternalLink class="h-3 w-3" />
                    </p>
                    <p v-if="token && !formatValid" class="text-xs text-amber-600">
                        The format doesn't look right. Double-check the value before connecting.
                    </p>
                </div>

                <p v-if="error" class="text-destructive border-destructive/40 bg-destructive/5 rounded-md border p-2 text-sm">
                    {{ error }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)"> Cancel </Button>
                    <Button type="submit" :disabled="saving || !token">
                        <LoaderCircle v-if="saving" class="mr-1 h-4 w-4 animate-spin" />
                        Connect
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
