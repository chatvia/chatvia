<script setup lang="ts">
/**
 * Renames the agent and changes its subdomain. Each time the dialog opens, the
 * fields are reset to the agent's current values and old errors are cleared.
 */
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { rename } from '@/routes/agents';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

const props = defineProps<{
    agentId: string;
    agentName: string;
    subDomain: string | null | undefined;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const page = usePage<SharedData>();

const renameForm = useForm({
    name: '',
    sub_domain: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        renameForm.name = props.agentName;
        renameForm.sub_domain = props.subDomain || '';
        renameForm.clearErrors();
    }
});

function renameAgent(): void {
    if (!renameForm.name.trim() || !renameForm.sub_domain.trim()) {
        return;
    }

    renameForm.name = renameForm.name.trim();
    renameForm.sub_domain = renameForm.sub_domain.trim().toLowerCase();

    renameForm.patch(rename.url(props.agentId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            renameForm.reset();
            toast.success(t('agents.nav.rename_dialog.updated_toast'));
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ t('agents.nav.rename_dialog.title') }}</DialogTitle>
                <DialogDescription>
                    {{ t('agents.nav.rename_dialog.description', { name: agentName }) }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div>
                    <label for="new-agent-name" class="text-foreground mb-2 block text-sm font-medium">
                        {{ t('agents.nav.rename_dialog.name_label') }}
                    </label>
                    <input
                        id="new-agent-name"
                        v-model="renameForm.name"
                        type="text"
                        class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring w-full rounded-md border px-3 py-2 focus:border-transparent focus:ring-2 focus:outline-none"
                        :class="{ 'border-red-500': renameForm.errors.name }"
                        :placeholder="t('agents.nav.rename_dialog.name_placeholder')"
                        @keyup.enter="renameAgent"
                    />
                    <p v-if="renameForm.errors.name" class="mt-1 text-xs text-red-500">
                        {{ renameForm.errors.name }}
                    </p>
                </div>

                <div>
                    <label for="subdomain" class="text-foreground mb-2 block text-sm font-medium">
                        {{ t('agents.nav.rename_dialog.subdomain_label') }}
                    </label>
                    <div class="flex">
                        <span
                            class="bg-muted border-input text-muted-foreground inline-flex items-center rounded-s-md border border-e-0 px-3 py-2 text-sm"
                        >
                            https://
                        </span>
                        <input
                            id="subdomain"
                            v-model="renameForm.sub_domain"
                            type="text"
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground focus:ring-ring flex-1 border-y px-3 py-2 text-sm focus:z-10 focus:ring-2 focus:outline-none"
                            :class="{ 'border-red-500': renameForm.errors.sub_domain }"
                            :placeholder="t('agents.nav.rename_dialog.subdomain_placeholder')"
                            @keyup.enter="renameAgent"
                        />
                        <span
                            class="bg-muted border-input text-muted-foreground inline-flex items-center rounded-e-md border border-s-0 px-3 py-2 text-sm"
                        >
                            .{{ page.props.domain }}
                        </span>
                    </div>
                    <p v-if="renameForm.errors.sub_domain" class="mt-1 text-xs text-red-500">
                        {{ renameForm.errors.sub_domain }}
                    </p>
                    <p v-else class="text-muted-foreground mt-1 text-xs">{{ t('agents.nav.rename_dialog.subdomain_hint') }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ t('common.cancel') }}</Button>
                <Button @click="renameAgent" :disabled="renameForm.processing || !renameForm.name.trim() || !renameForm.sub_domain.trim()">
                    <LoaderCircle v-if="renameForm.processing" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('agents.nav.rename_dialog.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
