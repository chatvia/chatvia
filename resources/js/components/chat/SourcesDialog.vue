<script setup lang="ts">
/**
 * "N Sources" button under an assistant reply, opening a dialog with the
 * knowledge-base chunks the answer was grounded in.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { MessageSource } from '@/types/chat';
import { FileText } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    sources: MessageSource[];
}>();

const { t } = useI18n();

const sourcesCount = computed(() => props.sources?.length || 0);

function formatSourceTitle(source: MessageSource, index: number): string {
    const metadata = source.metadata;
    if (metadata?.title) return metadata.title;

    if (metadata?.file_name) {
        return metadata.page ? t('sources.file_with_page', { file: metadata.file_name, page: metadata.page }) : metadata.file_name;
    }

    return t('sources.fallback_title', { number: index + 1 });
}

function formatSourceSubtitle(source: MessageSource): string | null {
    const parts: string[] = [];
    if (source.metadata?.chunk_index !== undefined) parts.push(t('sources.chunk_label', { index: source.metadata.chunk_index }));
    if (source.metadata?.page !== undefined) parts.push(t('sources.page_label', { page: source.metadata.page }));

    return parts.length > 0 ? parts.join(' • ') : null;
}
</script>

<template>
    <Dialog>
        <DialogTrigger asChild>
            <Button variant="outline" size="sm" class="h-7 px-2 text-xs" :disabled="sourcesCount === 0">
                <FileText class="me-1 h-3 w-3" />
                {{ t('sources.source_count', { count: sourcesCount }, { plural: sourcesCount }) }}
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[80vh] max-w-4xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    {{ t('sources.title') }}
                </DialogTitle>
                <DialogDescription>{{ t('sources.description', { count: sourcesCount }, { plural: sourcesCount }) }}</DialogDescription>
            </DialogHeader>

            <ScrollArea class="max-h-[60vh] pe-4">
                <div class="space-y-4">
                    <div v-for="(source, index) in sources" :key="index" class="space-y-3 rounded-lg border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <h4 class="truncate text-sm font-medium">
                                    {{ formatSourceTitle(source, index) }}
                                </h4>
                                <p v-if="formatSourceSubtitle(source)" class="text-muted-foreground mt-1 text-xs">
                                    {{ formatSourceSubtitle(source) }}
                                </p>
                            </div>
                            <Badge variant="secondary" class="text-xs">
                                {{ index + 1 }}
                            </Badge>
                        </div>

                        <div class="text-muted-foreground bg-muted/30 border-primary/20 rounded border-s-2 p-3 text-sm">
                            <p class="whitespace-pre-wrap">{{ source.content }}</p>
                        </div>

                        <div v-if="source.metadata" class="flex flex-wrap gap-1">
                            <Badge v-if="source.metadata.file_name" variant="outline" class="text-xs">
                                {{ source.metadata.file_name }}
                            </Badge>
                            <Badge v-if="source.metadata.page" variant="outline" class="text-xs">{{
                                t('sources.page_label', { page: source.metadata.page })
                            }}</Badge>
                        </div>
                    </div>
                </div>
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
