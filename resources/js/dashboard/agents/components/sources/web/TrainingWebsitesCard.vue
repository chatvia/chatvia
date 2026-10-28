<script setup lang="ts">
/** "Training": websites whose links are being trained right now. Their links can be viewed, or the training cancelled by deleting. */
import SourceActionButton from '@/dashboard/agents/components/sources/SourceActionButton.vue';
import WebsiteInfo from '@/dashboard/agents/components/sources/web/WebsiteInfo.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/shared/ui/card';
import type { WebSource } from '@/dashboard/agents/composables/sources/web/useWebSources';
import { BrainIcon, Link2, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    sources: WebSource[];
}>();

const emit = defineEmits<{
    viewLinks: [source: WebSource];
    delete: [source: WebSource];
}>();

const { t } = useI18n();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <BrainIcon class="h-5 w-5" />
                {{ t('agents.sources.web.training_title') }}
            </CardTitle>
            <CardDescription>
                {{ t('agents.sources.web.training_count', sources.length) }}
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div class="space-y-2">
                <div v-for="source in sources" :key="source.id" class="flex items-center justify-between rounded-lg border p-3">
                    <WebsiteInfo :source="source">
                        <template #details>
                            <div v-if="source.status === 'ingesting'" class="mt-2 flex items-center gap-2 text-blue-600">
                                <LoaderCircle class="h-3.5 w-3.5 animate-spin" />
                                <span class="text-xs">{{ t('agents.sources.web.training_in_progress') }}</span>
                            </div>
                        </template>
                    </WebsiteInfo>
                    <div class="flex items-center gap-1">
                        <SourceActionButton
                            :icon="Link2"
                            :label="t('agents.sources.web.view_all_links')"
                            class="text-muted-foreground hover:text-foreground h-8 w-8 flex-shrink-0 p-0"
                            @click="emit('viewLinks', source)"
                        />
                        <SourceActionButton
                            :icon="Trash2"
                            :label="t('agents.sources.web.delete_cancel_training')"
                            class="text-destructive hover:text-destructive/80 h-8 w-8 flex-shrink-0 p-0"
                            @click="emit('delete', source)"
                        />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
