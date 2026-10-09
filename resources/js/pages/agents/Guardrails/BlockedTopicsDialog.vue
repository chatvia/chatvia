<script setup lang="ts">
// "Blocked topics" dialog: an add/remove list of topics the agent must refuse.
// It edits a draft taken each time the dialog opens; "Done" writes the
// trimmed, de-duplicated list back, while closing any other way discards it.
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Ban, Check, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const open = defineModel<boolean>('open', { required: true });
const topics = defineModel<string[]>({ required: true });

const { t } = useI18n();

const draft = ref<string[]>([]);

watch(open, (isOpen) => {
    if (isOpen) draft.value = topics.value.length ? [...topics.value] : [''];
});

const addTopic = () => draft.value.push('');
const removeTopic = (index: number) => draft.value.splice(index, 1);

const apply = () => {
    const cleaned = draft.value.map((topic) => topic.trim()).filter(Boolean);
    topics.value = [...new Set(cleaned)];
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t('agents.guardrails.blocked_topics') }}</DialogTitle>
                <DialogDescription>{{ t('agents.guardrails.blocked_topics_dialog_description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-2">
                <div v-for="(topic, index) in draft" :key="index" class="flex items-center gap-2">
                    <div class="relative flex-1">
                        <Ban class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                        <Input
                            v-model="draft[index]"
                            :placeholder="
                                index === 0 ? t('agents.guardrails.topic_placeholder_first') : t('agents.guardrails.topic_placeholder_more')
                            "
                            class="ps-9"
                            @keydown.enter.prevent="addTopic"
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-muted-foreground hover:text-destructive shrink-0"
                        :aria-label="t('agents.guardrails.remove_topic')"
                        @click="removeTopic(index)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>

                <p v-if="!draft.length" class="text-muted-foreground text-sm">{{ t('agents.guardrails.no_topics_draft') }}</p>
            </div>

            <Button type="button" variant="outline" size="sm" class="w-fit" @click="addTopic">
                <Plus class="me-2 h-4 w-4" />
                {{ t('agents.guardrails.add_topic') }}
            </Button>

            <DialogFooter>
                <Button type="button" variant="ghost" @click="open = false">
                    <X class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.cancel') }}
                </Button>
                <Button type="button" @click="apply">
                    <Check class="me-2 h-4 w-4" />
                    {{ t('agents.guardrails.done') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
