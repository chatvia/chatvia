<script setup lang="ts">
// "Blocked topics" dialog: the topics the agent must not discuss. Each topic
// has a name, an optional description and example questions (they tell the
// safety check what the topic covers), and what the visitor gets instead:
// the refusal, the topic's own reply, or a hand-off to the team. It edits a
// draft taken each time the dialog opens; "Done" writes the cleaned list
// back, while closing any other way discards it. The hand-off needs a license
// key (live chat): without one it cannot be picked, and a topic that already
// uses it gets the refusal in chats.
import { Button } from '@/shared/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/shared/ui/dialog';
import { Input } from '@/shared/ui/input';
import { Label } from '@/shared/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/ui/select';
import { Textarea } from '@/shared/ui/textarea';
import { useLicense } from '@/dashboard/composables/useLicense';
import { Ban, Check, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { TOPIC_ACTION_LABELS } from './labels';
import type { BlockedTopic, TopicAction } from './types';

/** A topic as the dialog edits it: the examples as one text, one per line. */
interface TopicDraft {
    name: string;
    description: string;
    examples: string;
    action: TopicAction;
    reply: string;
}

const open = defineModel<boolean>('open', { required: true });
const topics = defineModel<BlockedTopic[]>({ required: true });

const { t } = useI18n();
const { licensed } = useLicense();

const ACTIONS: TopicAction[] = ['refuse', 'reply', 'handoff'];

const draft = ref<TopicDraft[]>([]);

const blank = (): TopicDraft => ({ name: '', description: '', examples: '', action: 'refuse', reply: '' });

watch(open, (isOpen) => {
    if (!isOpen) return;

    draft.value = topics.value.length
        ? topics.value.map((topic) => ({
              name: topic.name,
              description: topic.description ?? '',
              examples: topic.examples.join('\n'),
              action: topic.action,
              reply: topic.reply ?? '',
          }))
        : [blank()];
});

const addTopic = () => draft.value.push(blank());
const removeTopic = (index: number) => draft.value.splice(index, 1);

const apply = () => {
    const seen = new Set<string>();

    topics.value = draft.value
        .map((topic) => ({
            name: topic.name.trim(),
            description: topic.description.trim() || null,
            examples: topic.examples
                .split('\n')
                .map((example) => example.trim())
                .filter(Boolean)
                .slice(0, 5),
            action: topic.action,
            reply: topic.action === 'refuse' ? null : topic.reply.trim() || null,
        }))
        .filter((topic) => {
            const key = topic.name.toLowerCase();
            if (topic.name === '' || seen.has(key)) return false;
            seen.add(key);
            return true;
        });
    open.value = false;
};
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t('agents.guardrails.blocked_topics') }}</DialogTitle>
                <DialogDescription>{{ t('agents.guardrails.blocked_topics_dialog_description') }}</DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <div v-for="(topic, index) in draft" :key="index" class="space-y-2 rounded-md border p-3">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <Ban class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                            <Input
                                v-model="topic.name"
                                :placeholder="
                                    index === 0 ? t('agents.guardrails.topic_placeholder_first') : t('agents.guardrails.topic_placeholder_more')
                                "
                                :aria-label="t('agents.guardrails.topic_name')"
                                class="ps-9"
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

                    <Input
                        v-model="topic.description"
                        :placeholder="t('agents.guardrails.topic_description_placeholder')"
                        :aria-label="t('agents.guardrails.topic_description')"
                    />

                    <Textarea
                        v-model="topic.examples"
                        rows="2"
                        :placeholder="t('agents.guardrails.topic_examples_placeholder')"
                        :aria-label="t('agents.guardrails.topic_examples')"
                    />

                    <div class="space-y-1.5">
                        <Label class="text-xs">{{ t('agents.guardrails.topic_action') }}</Label>
                        <Select v-model="topic.action">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="action in ACTIONS" :key="action" :value="action" :disabled="action === 'handoff' && !licensed">
                                    {{ t(TOPIC_ACTION_LABELS[action]) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="topic.action === 'handoff'" class="text-muted-foreground text-xs">
                            {{ licensed ? t('agents.guardrails.topic_handoff_help') : t('license.unavailable') }}
                        </p>
                    </div>

                    <Textarea
                        v-if="topic.action !== 'refuse'"
                        v-model="topic.reply"
                        rows="2"
                        :placeholder="
                            topic.action === 'handoff'
                                ? t('agents.guardrails.topic_handoff_reply_placeholder')
                                : t('agents.guardrails.topic_reply_placeholder')
                        "
                        :aria-label="t('agents.guardrails.topic_reply')"
                    />
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
