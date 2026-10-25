<script setup lang="ts">
/**
 * The card a citation chip opens: the source's number and name (with the
 * page of a PDF), the start of the passage the answer drew on, and a link to
 * a website page or video. Stored files are never linked. Placed under the
 * chip, kept inside the window, and closed by the body that opened it. It is
 * moved to <body>: a chat column with a CSS transform would otherwise place
 * a fixed element against itself instead of the window.
 */
import type { MessageSource } from '@/types/chat';
import { AlignLeft, ExternalLink, FileAudio, FileText, Globe, PlayCircle } from 'lucide-vue-next';
import { computed, type Component } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    source: MessageSource;
    number: number;
    /** The chip's box on screen (getBoundingClientRect). */
    anchor: DOMRect;
}>();

const emit = defineEmits<{
    enter: [];
    leave: [];
}>();

const { t } = useI18n();

const KIND_ICONS: Record<MessageSource['kind'], Component> = {
    file: FileText,
    media: FileAudio,
    website: Globe,
    text: AlignLeft,
    video: PlayCircle,
};

const WIDTH = 288;

const title = computed(() =>
    props.source.page ? t('sources.file_with_page', { file: props.source.label, page: props.source.page }) : props.source.label,
);

const position = computed(() => {
    const left = Math.min(Math.max(8, props.anchor.left + props.anchor.width / 2 - WIDTH / 2), window.innerWidth - WIDTH - 8);
    const below = props.anchor.bottom + 6;
    // Above the chip when there is no room below it.
    return below + 180 > window.innerHeight
        ? { left: `${left}px`, bottom: `${window.innerHeight - props.anchor.top + 6}px` }
        : { left: `${left}px`, top: `${below}px` };
});
</script>

<template>
    <Teleport to="body">
        <div
            role="dialog"
            :aria-label="title"
            class="bg-popover text-popover-foreground fixed z-50 w-72 rounded-xl border p-3 text-start shadow-lg"
            :style="position"
            @mouseenter="emit('enter')"
            @mouseleave="emit('leave')"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    class="bg-muted text-muted-foreground inline-flex h-5 min-w-5 shrink-0 items-center justify-center rounded px-1 text-xs font-medium"
                    >{{ number }}</span
                >
                <component :is="KIND_ICONS[source.kind] ?? FileText" class="text-muted-foreground h-4 w-4 shrink-0" />
                <span dir="auto" class="truncate text-sm font-medium" :title="title">{{ title }}</span>
            </div>
            <p v-if="source.excerpt" dir="auto" class="text-muted-foreground mt-2 line-clamp-4 text-xs leading-relaxed">{{ source.excerpt }}</p>
            <a
                v-if="source.url"
                :href="source.url"
                target="_blank"
                rel="noopener noreferrer"
                class="text-primary mt-2 inline-flex items-center gap-1 text-xs font-medium hover:underline"
            >
                <ExternalLink class="h-3 w-3" />
                {{ t('sources.open_source') }}
            </a>
        </div>
    </Teleport>
</template>
