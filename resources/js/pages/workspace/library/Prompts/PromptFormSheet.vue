<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import DeleteConfirmationDialog from '@/components/ui/DeleteConfirmationDialog.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Copy, Hash, LoaderCircle, Save, Trash2 } from 'lucide-vue-next';
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
        form.patch(route('library.prompts.update', { external_id: props.prompt.id }), {
            preserveScroll: true,
            onSuccess: () => {
                emit('update:open', false);
                emit('changed');
            },
        });
    } else {
        form.post(route('library.prompts.store'), {
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
    router.delete(route('library.prompts.destroy', { external_id: props.prompt.id }), {
        preserveScroll: true,
        onSuccess: () => emit('changed'),
    });
    deleteOpen.value = false;
}

const idCopied = ref(false);

async function copyId(): Promise<void> {
    if (!props.prompt) return;
    await navigator.clipboard.writeText(props.prompt.id);
    idCopied.value = true;
    setTimeout(() => (idCopied.value = false), 2000);
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
                <div v-if="isEdit" class="bg-muted/40 flex items-center gap-2 rounded-md border px-3 py-2">
                    <span class="text-muted-foreground text-xs">{{ t('workspace.prompts.sheet.id_label') }}</span>
                    <code class="min-w-0 flex-1 truncate font-mono text-xs" :title="prompt?.id">{{ prompt?.id }}</code>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-6 shrink-0 px-1.5"
                        :title="t('workspace.prompts.sheet.copy_id_title')"
                        @click="copyId"
                    >
                        <Check v-if="idCopied" class="size-3.5 text-green-600" />
                        <Copy v-else class="size-3.5" />
                    </Button>
                </div>

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
