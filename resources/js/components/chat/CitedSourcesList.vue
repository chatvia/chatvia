<script setup lang="ts">
/**
 * The numbered sources under an answer that cites them, in the order the
 * answer first cites them, so each number matches its chip in the text.
 * Website pages and videos link out; stored files are never linked.
 */
import type { CitedSources } from '@/lib/citations';
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    cited: CitedSources;
}>();

const { t } = useI18n();

const entries = computed(() => props.cited.ordered.filter((entry) => entry.number !== null));

function title(entry: CitedSources['ordered'][number]): string {
    const { label, page } = entry.source;
    return page ? t('sources.file_with_page', { file: label, page }) : label;
}
</script>

<template>
    <ol v-if="entries.length > 0" class="mt-3 flex flex-wrap gap-1.5" :aria-label="t('sources.title')">
        <li v-for="entry in entries" :key="entry.number ?? 0" class="min-w-0">
            <component
                :is="entry.source.url ? 'a' : 'span'"
                :href="entry.source.url ?? undefined"
                :target="entry.source.url ? '_blank' : undefined"
                :rel="entry.source.url ? 'noopener noreferrer' : undefined"
                class="bg-muted/60 text-muted-foreground inline-flex max-w-64 items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs"
                :class="{ 'hover:text-foreground hover:bg-muted transition-colors': entry.source.url }"
            >
                <span class="font-medium tabular-nums">{{ entry.number }}</span>
                <span dir="auto" class="truncate" :title="title(entry)">{{ title(entry) }}</span>
                <ExternalLink v-if="entry.source.url" class="h-3 w-3 shrink-0" aria-hidden="true" />
            </component>
        </li>
    </ol>
</template>
