<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LayoutTemplate, Link2Off } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

import ConfirmationDialog from '@/components/chat/ConfirmationDialog.vue';
import { Button } from '@/components/ui/button';

interface TemplateOption {
    id: string;
    name: string;
}

const props = defineProps<{
    agentId: string;
    attachedTemplate: TemplateOption | null;
}>();

const busy = ref(false);

function detach(): void {
    if (busy.value) return;
    busy.value = true;
    router.delete(route('agents.template.destroy', { agent: props.agentId }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Template detached'),
        onError: () => toast.error('Could not detach template'),
        onFinish: () => (busy.value = false),
    });
}
</script>

<template>
    <div v-if="attachedTemplate" class="mb-6 rounded-lg border p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="bg-muted flex h-9 w-9 shrink-0 items-center justify-center rounded-md">
                    <LayoutTemplate class="text-muted-foreground h-5 w-5" />
                </div>
                <div>
                    <p class="text-sm font-medium">Using template "{{ attachedTemplate.name }}"</p>
                    <p class="text-muted-foreground text-xs">These settings are managed by the template. Detach to edit them on this agent.</p>
                </div>
            </div>
            <ConfirmationDialog
                title="Detach template?"
                description="Its current settings will be copied onto the agent."
                confirm-text="Detach"
                @confirm="detach"
            >
                <template #trigger>
                    <Button variant="outline" size="sm" :disabled="busy">
                        <Link2Off class="mr-1 h-4 w-4" />
                        Detach
                    </Button>
                </template>
            </ConfirmationDialog>
        </div>
    </div>
</template>
