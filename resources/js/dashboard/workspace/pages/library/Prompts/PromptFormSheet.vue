<script setup lang="ts">
/**
 * Create / edit sheet for a reusable library prompt that agents can pick up.
 * Editing shows the prompt's id (for the public API) and a Delete action
 * behind a confirmation.
 */
import CopyIdField from '@/dashboard/workspace/components/CopyIdField.vue';
import DeleteConfirmationDialog from '@/dashboard/components/DeleteConfirmationDialog.vue';
import InputError from '@/shared/components/InputError.vue';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/shared/ui/sheet';
import { Textarea } from '@/shared/ui/textarea';
import { destroy as destroyPrompt, store as storePrompt, update as updatePrompt } from '@/routes/library/prompts';
import { router, useForm } from '@inertiajs/vue3';
import { Hash, LoaderCircle, Save, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

export interface PromptRow {
    id: string;
    name: string;
    prompt: string;
    agents_count: number;
    created_at: string | null;
}

interface Props {
    open: boolean;
    prompt: PromptRow | null;
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean]; changed: [] }>();

const { t } = useI18n();

const isEdit = computed(() => props.prompt !== null);

const form = useForm({
    name: '',
    prompt: '',
});

watch(
    () => props.open,
    (open) => {
        if (!open) return;
        form.clearErrors();
        if (props.prompt) {
            form.name = props.prompt.name;
            form.prompt = props.prompt.prompt;
        } else {
            form.reset();
        }
    },
);

function submit(): void {
    if (props.prompt) {
        form.patch(updatePrompt.url(props.prompt.id), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('changed');
            },
        });
    } else {
        form.post(storePrompt.url(), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('changed');
            },
        });
    }
}

const deleteOpen = ref(false);

function askDelete(): void {
    // Sheet + Dialog at the same layer overlap awkwardly; close the sheet first.
    emit('update:open', false);
    setTimeout(() => (deleteOpen.value = true), 150);
}

function confirmDelete(): void {
    if (!props.prompt) return;
    router.delete(destroyPrompt.url(props.prompt.id), {
        preserveScroll: true,
        onSuccess: () => emit('changed'),
    });
    deleteOpen.value = false;
}
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent class="w-full overflow-y-auto p-0 sm:max-w-xl">
            <SheetHeader class="border-b p-6">
                <SheetTitle>{{ isEdit ? t('workspace.prompts.sheet.edit_title') : t('workspace.prompts.sheet.create_title') }}</SheetTitle>
                <SheetDescription>
                    {{ t('workspace.prompts.sheet.description') }}
                </SheetDescription>
            </SheetHeader>

            <form @submit.prevent="submit" class="space-y-5 px-6 pt-6">
                <CopyIdField
                    v-if="isEdit && prompt"
                    :label="t('workspace.prompts.sheet.id_label')"
                    :value="prompt.id"
                    :copy-title="t('workspace.prompts.sheet.copy_id_title')"
                    :copy-label="t('workspace.prompts.sheet.copy_id')"
                />

                <div class="grid gap-2">
                    <Label for="name">{{ t('workspace.prompts.form.name') }}</Label>
                    <div class="relative">
                        <Hash class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2" />
                        <Input id="name" v-model="form.name" :placeholder="t('workspace.prompts.form.name_placeholder')" class="ps-9" required />
                    </div>
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="prompt">{{ t('workspace.prompts.form.prompt') }}</Label>
                    <Textarea
                        id="prompt"
                        v-model="form.prompt"
                        :error="form.errors.prompt"
                        :placeholder="t('workspace.prompts.form.prompt_placeholder')"
                        rows="16"
                    />
                    <p class="text-muted-foreground text-xs">{{ t('workspace.prompts.form.prompt_hint') }}</p>
                    <InputError :message="form.errors.prompt" />
                </div>

                <div class="flex items-center justify-end gap-2 border-t pt-5">
                    <Button v-if="isEdit" type="button" variant="ghost" class="text-destructive hover:text-destructive me-auto" @click="askDelete">
                        <Trash2 class="me-2 size-4" />
                        {{ t('workspace.common.delete') }}
                    </Button>
                    <Button type="button" variant="outline" @click="emit('update:open', false)">{{ t('workspace.common.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="me-2 size-4 animate-spin" />
                        <Save v-else class="me-2 size-4" />
                        {{ isEdit ? t('workspace.common.save') : t('workspace.prompts.add') }}
                    </Button>
                </div>
            </form>

            <div class="h-6"></div>
        </SheetContent>
    </Sheet>

    <DeleteConfirmationDialog
        :open="deleteOpen"
        :title="t('workspace.prompts.delete_confirm_title', { name: prompt?.name ?? t('workspace.prompts.fallback_name') })"
        :description="t('workspace.prompts.delete_confirm_description')"
        :confirm-text="t('workspace.common.delete')"
        :cancel-text="t('workspace.common.keep')"
        @cancel="deleteOpen = false"
        @confirm="confirmDelete"
    />
</template>
