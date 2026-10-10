<script lang="ts" setup>
/**
 * "Create agent" dialog: a name field that creates the agent and tells the
 * page to reload its numbers.
 */
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/agents';
import { useForm } from '@inertiajs/vue3';
import { Bot, LoaderCircle, Plus } from 'lucide-vue-next';
import { nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    'agent-created': [];
}>();

const form = useForm({
    name: '',
});

const isSubmitting = ref(false);

const focusNameInput = () => {
    nextTick(() => {
        if (props.open) {
            document.getElementById('name')?.focus();
        }
    });
};

const submit = () => {
    isSubmitting.value = true;

    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitting.value = false;
            emit('update:open', false);
            emit('agent-created');
            form.reset();
            form.clearErrors();
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const closeModal = () => {
    emit('update:open', false);
    form.reset();
    form.clearErrors();
};

const onOpenAutoFocus = (event: Event) => {
    // Stop Reka from focusing the close button, then focus the name input.
    event.preventDefault();
    focusNameInput();
};

watch(
    () => props.open,
    (newValue) => {
        if (newValue) {
            form.clearErrors();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(value) => emit('update:open', value)">
        <DialogContent class="max-w-lg" @open-auto-focus="onOpenAutoFocus">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Bot class="h-5 w-5" />
                    {{ t('agents.create_modal.title') }}
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit">
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">{{ form.progress.percentage }}%</progress>
                <div class="space-y-6">
                    <div class="space-y-2">
                        <Label for="name" required>{{ t('agents.create_modal.name_label') }}</Label>
                        <div class="relative">
                            <Bot class="text-muted-foreground pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input
                                id="name"
                                v-model="form.name"
                                :error="form.errors.name"
                                class="ps-9"
                                :placeholder="t('agents.create_modal.name_placeholder')"
                                autocomplete="off"
                                data-1p-ignore
                                data-lpignore="true"
                                data-form-type="other"
                            />
                        </div>
                        <p v-if="form.errors.name" class="text-destructive text-sm">{{ form.errors.name }}</p>
                    </div>
                </div>
            </form>

            <div class="my-4 border-t border-gray-200 dark:border-gray-700"></div>

            <DialogFooter class="flex justify-between">
                <Button variant="outline" size="lg" @click="closeModal">{{ t('common.cancel') }}</Button>
                <Button :disabled="isSubmitting" size="lg" @click="submit">
                    <LoaderCircle v-if="isSubmitting" class="me-2 h-4 w-4 animate-spin" />
                    <Plus v-else class="me-2 h-4 w-4" />
                    {{ t('agents.create_modal.title') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
