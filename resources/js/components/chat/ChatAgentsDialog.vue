<script setup lang="ts">
/**
 * "My agents" dialog for a chatter who can use several agents. Lists them,
 * preselects the agent of the current subdomain and switches to the chosen one.
 */
import { fetchChatterAgents } from '@/api/chat';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { ChatterAgent } from '@/types/chat';
import { useForm } from '@inertiajs/vue3';
import { Bot } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();

const agents = ref<ChatterAgent[]>([]);
const selectedAgent = ref<ChatterAgent | null>(null);
const isLoadingAgents = ref(false);
const agentSwitchForm = useForm({});

/** "translation" from "translation.example.com"; null on a bare domain. */
function currentSubdomain(): string | null {
    const parts = window.location.hostname.split('.');

    return parts.length > 2 ? parts[0] : null;
}

async function loadAgents() {
    if (isLoadingAgents.value) return;

    isLoadingAgents.value = true;
    try {
        agents.value = await fetchChatterAgents();

        if (!selectedAgent.value && agents.value.length > 0) {
            const subdomain = currentSubdomain();
            const current = subdomain ? agents.value.find((agent) => agent.sub_domain === subdomain) : undefined;
            selectedAgent.value = current || agents.value[0];
        }
    } catch {
        // The dialog shows its empty state; the visitor can close and retry.
    } finally {
        isLoadingAgents.value = false;
    }
}

// Refresh the list every time the dialog opens.
watch(open, (isOpen) => {
    if (isOpen) void loadAgents();
});

function switchToSelectedAgent() {
    if (!selectedAgent.value || agentSwitchForm.processing) return;

    agentSwitchForm.post(route('chat.agents.switch', { agent: selectedAgent.value.external_id }), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>{{ t('sidebar.agents_dialog_title') }}</DialogTitle>
                <DialogDescription>{{ t('sidebar.agents_dialog_description') }}</DialogDescription>
            </DialogHeader>
            <ScrollArea class="max-h-[400px] py-4">
                <div class="space-y-4">
                    <div v-if="isLoadingAgents" class="py-8 text-center">
                        <svg
                            class="text-muted-foreground mx-auto mb-4 h-6 w-6 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path
                                class="opacity-75"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                fill="currentColor"
                            ></path>
                        </svg>
                        <p class="text-muted-foreground text-sm">{{ t('sidebar.loading_agents') }}</p>
                    </div>
                    <div v-else-if="agents.length === 0" class="py-8 text-center">
                        <Bot class="text-muted-foreground mx-auto mb-4 h-12 w-12" />
                        <p class="text-muted-foreground">{{ t('sidebar.no_agents') }}</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="agent in agents"
                            :key="agent.id"
                            :class="[
                                'flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors',
                                selectedAgent?.id === agent.id ? 'border-primary bg-primary/5' : 'hover:bg-accent',
                            ]"
                            role="button"
                            tabindex="0"
                            @click="selectedAgent = agent"
                            @keydown.enter="selectedAgent = agent"
                            @keydown.space.prevent="selectedAgent = agent"
                        >
                            <div class="flex flex-1 items-center gap-3">
                                <div
                                    :class="[
                                        'h-4 w-4 rounded-full border-2',
                                        selectedAgent?.id === agent.id ? 'border-primary bg-primary' : 'border-muted-foreground',
                                    ]"
                                >
                                    <div v-if="selectedAgent?.id === agent.id" class="m-0.5 h-2 w-2 rounded-full bg-white"></div>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold">{{ agent.name }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </ScrollArea>
            <DialogFooter class="flex-col gap-2 sm:flex-row">
                <Button @click="switchToSelectedAgent" :disabled="!selectedAgent || agentSwitchForm.processing" class="w-full sm:w-auto">
                    <svg
                        v-if="agentSwitchForm.processing"
                        class="me-2 h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path
                            class="opacity-75"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 4 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            fill="currentColor"
                        ></path>
                    </svg>
                    <Bot v-else class="me-2 h-4 w-4" />
                    {{
                        agentSwitchForm.processing ? t('sidebar.switching_agent') : t('sidebar.switch_to_agent', { name: selectedAgent?.name || '' })
                    }}
                </Button>
                <Button variant="outline" @click="open = false" class="w-full sm:w-auto">{{ t('common.close') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
