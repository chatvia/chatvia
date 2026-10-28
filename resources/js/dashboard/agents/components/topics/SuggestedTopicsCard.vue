<script setup lang="ts">
/**
 * Topics the conversation analysis keeps finding that the agent does not have
 * yet, each with a few example conversations. Adding one turns it into a real
 * topic; dismissing it (after a confirmation the page shows) hides it.
 */
import type { TopicSuggestion } from '@/dashboard/agents/api/topics';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/shared/ui/card';
import { LoaderCircle, Plus, Sparkles, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    suggestions: TopicSuggestion[];
    /** The label being promoted right now, if any. */
    promotingLabel: string | null;
}>();

const emit = defineEmits<{
    promote: [label: string];
    dismiss: [label: string];
}>();

const { t } = useI18n();

const exampleTitles = (suggestion: TopicSuggestion): string =>
    suggestion.examples
        .slice(0, 3)
        .map((e) => e.title || t('agents.topics_index.untitled'))
        .join(' · ');
</script>

<template>
    <Card class="mb-6">
        <CardHeader>
            <div class="flex items-center gap-2">
                <Sparkles class="text-primary h-4 w-4" />
                <CardTitle>{{ t('agents.topics_index.suggested_title') }}</CardTitle>
            </div>
            <p class="text-muted-foreground text-sm">
                {{ t('agents.topics_index.suggested_description') }}
            </p>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div v-for="s in suggestions" :key="s.label" class="flex items-start justify-between gap-4 rounded-lg border p-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">{{ s.label }}</span>
                            <Badge variant="secondary" class="text-xs"> {{ s.count }} {{ t('agents.topics_index.conversation', s.count) }} </Badge>
                        </div>
                        <p v-if="s.examples.length" class="text-muted-foreground mt-1 truncate text-xs">
                            {{ t('agents.topics_index.examples', { titles: exampleTitles(s) }) }}
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 gap-2">
                        <Button @click="emit('promote', s.label)" :disabled="promotingLabel === s.label" size="sm">
                            <LoaderCircle v-if="promotingLabel === s.label" class="me-1 h-4 w-4 animate-spin" />
                            <Plus v-else class="me-1 h-4 w-4" />
                            {{ t('agents.topics_index.add') }}
                        </Button>
                        <Button @click="emit('dismiss', s.label)" variant="outline" size="sm">
                            <X class="me-1 h-4 w-4" />
                            {{ t('agents.topics_index.dismiss') }}
                        </Button>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
