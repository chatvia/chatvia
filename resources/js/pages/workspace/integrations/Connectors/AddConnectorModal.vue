<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { AgentMcpServer } from '@/types/connector';
import axios from 'axios';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

interface Props {
    open: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    added: [server: AgentMcpServer];
}>();

const { t } = useI18n();

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
        const response = await axios.post(route('integrations.connectors.custom.add'), {
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

        toast.success(t('workspace.connectors.add_modal.added', { name: name.value }));
        emit('added', response.data.data);
        emit('update:open', false);
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        error.value =
            err.response?.data?.message ?? Object.values(err.response?.data?.errors ?? {}).flat()[0] ?? t('workspace.connectors.add_modal.error');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('workspace.connectors.add_modal.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('workspace.connectors.add_modal.description') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4 py-2" @submit.prevent="submit">
                <div class="space-y-1.5">
                    <Label for="conn-name">{{ t('workspace.connectors.add_modal.display_name') }}</Label>
                    <Input
                        id="conn-name"
                        v-model="name"
                        :placeholder="t('workspace.connectors.add_modal.display_name_placeholder')"
                        :disabled="saving"
                        required
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="conn-url">{{ t('workspace.connectors.add_modal.server_url') }}</Label>
                    <Input id="conn-url" v-model="url" type="url" placeholder="https://mcp.example.com" :disabled="saving" required />
                </div>

                <div class="space-y-2">
                    <Label>{{ t('workspace.connectors.add_modal.authentication') }}</Label>
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
                            {{
                                opt === 'oauth'
                                    ? t('workspace.connectors.add_modal.strategy_oauth')
                                    : opt === 'token'
                                      ? t('workspace.connectors.add_modal.strategy_token')
                                      : t('workspace.connectors.add_modal.strategy_none')
                            }}
                        </label>
                    </div>
                    <p class="text-muted-foreground text-xs">
                        <span v-if="strategy === 'oauth'">{{ t('workspace.connectors.add_modal.hint_oauth') }}</span>
                        <span v-else-if="strategy === 'token'">{{ t('workspace.connectors.add_modal.hint_token') }}</span>
                        <span v-else>{{ t('workspace.connectors.add_modal.hint_none') }}</span>
                    </p>
                </div>

                <div v-if="strategy === 'token'" class="space-y-1.5">
                    <Label for="conn-token">{{ t('workspace.connectors.add_modal.bearer_token') }}</Label>
                    <Input id="conn-token" v-model="token" type="password" autocomplete="off" :disabled="saving" />
                </div>

                <p v-if="error" class="text-destructive border-destructive/40 bg-destructive/5 rounded-md border p-2 text-sm">
                    {{ error }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="ghost" :disabled="saving" @click="emit('update:open', false)">{{
                        t('workspace.common.cancel')
                    }}</Button>
                    <Button type="submit" :disabled="saving || !canSubmit">
                        <LoaderCircle v-if="saving" class="me-1 h-4 w-4 animate-spin" />
                        {{ strategy === 'oauth' ? t('workspace.connectors.add_modal.continue_signin') : t('workspace.connectors.add_modal.submit') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
