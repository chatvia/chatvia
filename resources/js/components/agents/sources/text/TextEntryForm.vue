<script setup lang="ts">
/**
 * The create / edit form for a text source. With an `entryId` it edits that
 * text, starting from `initialContent`; without one it creates a new text.
 * Emits `saved` once the server accepted the text and `cancel` when the user
 * backs out. The parent mounts a fresh form for each open, so no reset is needed.
 */
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/vue3';
import { Edit3, LoaderCircle, Save } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        agentId: string;
        /** The text being edited; null for a new text. */
        entryId?: number | null;
        initialContent?: string;
    }>(),
    { entryId: null, initialContent: '' },
);

const emit = defineEmits<{
    saved: [];
    cancel: [];
}>();

const { t } = useI18n();

const form = useForm({
    content: props.initialContent,
});
const isSubmitting = ref(false);

const isEditing = computed(() => props.entryId !== null);

const wordCount = computed(() => {
    if (!form.content) return 0;
    return form.content
        .trim()
        .split(/\s+/)
        .filter((word) => word.length > 0).length;
});

const charCount = computed(() => (form.content ? form.content.length : 0));

function save(): void {
    isSubmitting.value = true;

    const callbacks = {
        onSuccess: () => {
            isSubmitting.value = false;
            emit('saved');
        },
        onError: () => {
            isSubmitting.value = false;
        },
    };

    if (props.entryId !== null) {
        form.patch(route('agents.sources.text.update', { agent: props.agentId, id: props.entryId }), callbacks);
    } else {
        form.post(route('agents.sources.text.store', { agent: props.agentId }), callbacks);
    }
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Edit3 class="h-5 w-5" />
                {{ isEditing ? t('agents.sources.text.edit_entry') : t('agents.sources.text.create_entry') }}
            </CardTitle>
            <CardDescription>
                {{ isEditing ? t('agents.sources.text.edit_entry_description') : t('agents.sources.text.create_entry_description') }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label for="content">{{ t('agents.sources.text.content_label') }}</Label>
                    <div class="text-muted-foreground flex gap-4 text-sm">
                        <span>{{ t('agents.sources.text.word_count', { count: wordCount }) }}</span>
                        <span>{{ t('agents.sources.text.char_count', { count: charCount }) }}</span>
                    </div>
                </div>
                <Textarea
                    id="content"
                    v-model="form.content"
                    :disabled="isSubmitting"
                    :placeholder="t('agents.sources.text.content_placeholder')"
                    rows="12"
                    :class="{ 'border-red-500': form.errors.content }"
                    class="resize-none"
                />
                <InputError :message="form.errors.content" />
                <p v-if="!form.errors.content" class="text-muted-foreground text-sm">
                    {{ t('agents.sources.text.content_hint') }}
                </p>
            </div>

            <div class="flex items-center justify-between border-t pt-4">
                <Button variant="outline" :disabled="isSubmitting" @click="emit('cancel')">
                    {{ t('agents.sources.common.cancel') }}
                </Button>
                <Button :disabled="isSubmitting" class="flex items-center gap-2" @click="save">
                    <LoaderCircle v-if="isSubmitting" class="h-4 w-4 animate-spin" />
                    <Save v-else class="h-4 w-4" />
                    {{
                        isSubmitting
                            ? t('agents.sources.text.saving')
                            : isEditing
                              ? t('agents.sources.text.update_entry')
                              : t('agents.sources.text.save_entry')
                    }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
