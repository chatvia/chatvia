<script setup lang="ts">
/**
 * Name + description form for adding or renaming a topic on the Manage Topics
 * page. The parent owns the values and the submit; Enter in the name field
 * submits too.
 */
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Textarea } from '@/shared/ui/textarea';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    /** Prefix of the field ids, so the add and edit dialogs never share one. */
    idPrefix: string;
    title: string;
    description: string;
    submitLabel: string;
    submitting: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });
const topic = defineModel<string>('topic', { required: true });
const topicDescription = defineModel<string>('topicDescription', { required: true });

const emit = defineEmits<{ submit: [] }>();

const { t } = useI18n();
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <div class="space-y-4">
                <div>
                    <Label :for="`${idPrefix}-topic`">{{ t('agents.topics.manage.topic_name') }}</Label>
                    <Input
                        :id="`${idPrefix}-topic`"
                        v-model="topic"
                        :placeholder="t('agents.topics.manage.topic_name_placeholder')"
                        @keydown.enter="emit('submit')"
                    />
                </div>
                <div>
                    <Label :for="`${idPrefix}-description`">{{ t('agents.topics.manage.description_label') }}</Label>
                    <Textarea
                        :id="`${idPrefix}-description`"
                        v-model="topicDescription"
                        :placeholder="t('agents.topics.manage.description_placeholder')"
                        rows="3"
                    />
                </div>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="open = false" :disabled="submitting">
                    {{ t('agents.topics.manage.cancel') }}
                </Button>
                <Button @click="emit('submit')" :disabled="submitting">
                    <LoaderCircle v-if="submitting" class="me-2 h-4 w-4 animate-spin" />
                    {{ submitLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
