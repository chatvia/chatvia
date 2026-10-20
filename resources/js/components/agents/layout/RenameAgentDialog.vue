<script setup lang="ts">
/**
 * Renames the agent and changes its chat address. Each time the dialog opens, the
 * fields are reset to the agent's current values and old errors are cleared.
 */
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    slug: string | null | undefined;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t } = useI18n();
const page = usePage<SharedData>();

const renameForm = useForm({
    name: '',
    slug: '',
});

watch(open, (isOpen) => {
    if (isOpen) {
        renameForm.name = props.agentName;
        renameForm.slug = props.slug || '';
        renameForm.clearErrors();
    }
});

function renameAgent(): void {
    if (!renameForm.name.trim() || !renameForm.slug.trim()) {
        return;
    }

    renameForm.name = renameForm.name.trim();
    renameForm.slug = renameForm.slug.trim().toLowerCase();

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
                <div class="grid gap-2">
                    <Label for="new-agent-name">{{ t('agents.nav.rename_dialog.name_label') }}</Label>
                    <Input
                        id="new-agent-name"
                        v-model="renameForm.name"
                        type="text"
                        :aria-invalid="!!renameForm.errors.name"
                        :placeholder="t('agents.nav.rename_dialog.name_placeholder')"
                        @keyup.enter="renameAgent"
                    />
                    <p v-if="renameForm.errors.name" class="text-destructive text-xs">
                        {{ renameForm.errors.name }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="slug">{{ t('agents.nav.rename_dialog.slug_label') }}</Label>
                    <div class="flex">
                        <span
                            class="bg-muted border-input text-muted-foreground inline-flex h-9 max-w-[60%] items-center truncate rounded-s-md border border-e-0 px-3 text-sm"
                            dir="ltr"
                        >
                            {{ page.props.chatBaseUrl }}/
                        </span>
                        <Input
                            id="slug"
                            v-model="renameForm.slug"
                            type="text"
                            dir="ltr"
                            class="min-w-0 flex-1 rounded-s-none focus-visible:z-10"
                            :aria-invalid="!!renameForm.errors.slug"
                            :placeholder="t('agents.nav.rename_dialog.slug_placeholder')"
                            @keyup.enter="renameAgent"
                        />
                    </div>
                    <p v-if="renameForm.errors.slug" class="text-destructive text-xs">
                        {{ renameForm.errors.slug }}
                    </p>
                    <p v-else class="text-muted-foreground text-xs">{{ t('agents.nav.rename_dialog.slug_hint') }}</p>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="open = false">{{ t('common.cancel') }}</Button>
                <Button @click="renameAgent" :disabled="renameForm.processing || !renameForm.name.trim() || !renameForm.slug.trim()">
                    <LoaderCircle v-if="renameForm.processing" class="me-2 h-4 w-4 animate-spin" />
                    {{ t('agents.nav.rename_dialog.save') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
