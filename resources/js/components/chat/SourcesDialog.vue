<script setup lang="ts">
/**
 * "N Sources" button under an assistant reply, opening a dialog with the
 * sources the answer was given: each one's name, the page of a PDF, how many
 * of its passages were used, and a link to a website page or a video. The
 * chat log also passes each passage's relevance and the start of its text,
 * and uses the smaller `compact` button in a message's details line.
 */
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import type { MessageSource } from '@/types/chat';
import { AlignLeft, ExternalLink, FileAudio, FileText, Globe, PlayCircle } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    sources: MessageSource[];
    compact?: boolean;
}>();

const { t } = useI18n();

const sourcesCount = computed(() => props.sources?.length || 0);

const kindIcons: Record<MessageSource['kind'], Component> = {
    file: FileText,
    media: FileAudio,
    website: Globe,
    text: AlignLeft,
    video: PlayCircle,
};

function sourceTitle(source: MessageSource, index: number): string {
    const label = source.label || t('sources.fallback_title', { number: index + 1 });

    return source.page ? t('sources.file_with_page', { file: label, page: source.page }) : label;
}
</script>

<template>
    <Dialog>
        <DialogTrigger asChild>
            <button
                v-if="compact"
                type="button"
                class="hover:text-foreground inline-flex cursor-pointer items-center gap-1 transition-colors"
                :disabled="sourcesCount === 0"
            >
                <FileText class="h-3 w-3" aria-hidden="true" />
                {{ t('sources.source_count', { count: sourcesCount }, { plural: sourcesCount }) }}
            </button>
            <Button v-else variant="outline" size="sm" class="h-7 px-2 text-xs" :disabled="sourcesCount === 0">
                <FileText class="me-1 h-3 w-3" />
                {{ t('sources.source_count', { count: sourcesCount }, { plural: sourcesCount }) }}
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[80vh] sm:max-w-xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <FileText class="h-5 w-5" />
                    {{ t('sources.title') }}
                </DialogTitle>
                <DialogDescription>{{ t('sources.description', { count: sourcesCount }, { plural: sourcesCount }) }}</DialogDescription>
            </DialogHeader>

            <!-- min-w-0: a grid item is otherwise as wide as its longest name, which pushes the rows past the dialog. -->
            <ScrollArea class="max-h-[60vh] min-w-0 pe-4">
                <ul class="space-y-2">
                    <li v-for="(source, index) in sources" :key="source.id ?? index" class="min-w-0 rounded-lg border p-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <Badge variant="secondary" class="shrink-0 text-xs">{{ index + 1 }}</Badge>
                            <component :is="kindIcons[source.kind] ?? FileText" class="text-muted-foreground h-4 w-4 shrink-0" />
                            <!-- The inner dir="auto" cuts a name at its own end (the left of an Arabic one); the outer span keeps the row's direction for me-auto. -->
                            <span class="me-auto flex min-w-0">
                                <span dir="auto" class="truncate text-sm font-medium" :title="sourceTitle(source, index)">
                                    {{ sourceTitle(source, index) }}
                                </span>
                            </span>
                            <span v-if="(source.passages ?? 1) > 1" class="text-muted-foreground shrink-0 text-xs">
                                {{ t('sources.passage_count', { count: source.passages }, { plural: source.passages }) }}
                            </span>
                            <a
                                v-if="source.url"
                                :href="source.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-muted-foreground hover:text-foreground shrink-0"
                                :aria-label="t('sources.open_link', { label: sourceTitle(source, index) })"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </a>
                        </div>
                        <p v-if="typeof source.score === 'number'" class="text-muted-foreground mt-2 text-xs tabular-nums">
                            {{ t('sources.relevance', { score: source.score.toFixed(2) }) }}
                        </p>
                        <p v-if="source.preview" dir="auto" class="text-muted-foreground mt-1 line-clamp-4 text-xs leading-relaxed break-words">
                            {{ source.preview }}
                        </p>
                        <p v-else-if="source.preview === null" class="text-muted-foreground mt-1 text-xs italic">
                            {{ t('sources.passage_removed') }}
                        </p>
                    </li>
                </ul>
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
