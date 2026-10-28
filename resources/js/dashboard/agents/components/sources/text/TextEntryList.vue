<script setup lang="ts">
/**
 * The card that lists the agent's texts, one TextEntryRow per text; further
 * pages load with "Load more".
 */
import type { TextSource } from '@/dashboard/agents/api/textSources';
import LoadMoreButton from '@/dashboard/agents/components/sources/LoadMoreButton.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import { FileText } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import TextEntryRow from './TextEntryRow.vue';

defineProps<{
    entries: TextSource[];
    /** How many texts the agent has, loaded or not. */
    total: number;
    hasMore: boolean;
    isLoadingMore: boolean;
}>();

const emit = defineEmits<{
    loadMore: [];
    edit: [entry: TextSource];
    remove: [entry: TextSource];
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <FileText class="h-5 w-5" />
                {{ t('agents.sources.text.entries_title', { count: total }) }}
            </CardTitle>
            <CardDescription>{{ t('agents.sources.text.entries_description') }}</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <TextEntryRow v-for="entry in entries" :key="entry.id" :entry="entry" @edit="emit('edit', entry)" @remove="emit('remove', entry)" />
            <LoadMoreButton v-if="hasMore" :is-loading="isLoadingMore" @click="emit('loadMore')" />
        </CardContent>
    </Card>
</template>
