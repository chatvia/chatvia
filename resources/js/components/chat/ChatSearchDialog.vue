<script lang="ts" setup>
/**
 * ⌘K command palette for the hosted chat: "New chat" plus recent
 * conversations grouped by date, or server search hits once the visitor types.
 * Fully keyboard navigable (arrows + Enter).
 */
import type { ChatConversationSummary as Conversation } from '@/types/chat';
import dayjs from 'dayjs';
import { Edit, Loader2, MessageSquare, Search } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';

const props = defineProps<{
    open: boolean;
    // Recents, shown when the query is empty.
    conversations: Conversation[];
    // Server search across title + message content (full-text). Returns the hits.
    search: (query: string) => Promise<Conversation[]>;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    select: [id: string | number];
    'new-chat': [];
}>();

const { t } = useI18n();

const query = ref('');
const results = ref<Conversation[]>([]);
const loading = ref(false);
const activeIndex = ref(0);
const searchInput = ref<HTMLInputElement | null>(null);

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
// Monotonic request id so a slow response can't overwrite a newer query's hits.
let requestSeq = 0;

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const hasQuery = computed(() => query.value.trim() !== '');

// Recents when the field is empty; server hits (title + content) once typing.
const displayItems = computed(() => (hasQuery.value ? results.value : props.conversations));

// Reset state and focus the field each time the palette opens.
watch(
    () => props.open,
    (open) => {
        if (open) {
            query.value = '';
            results.value = [];
            loading.value = false;
            activeIndex.value = 0;
            nextTick(() => searchInput.value?.focus());
        }
    },
);

// Debounced server search; recents show instantly when the field is cleared.
watch(query, (value) => {
    const term = value.trim();
    activeIndex.value = 0;
    if (debounceTimer) clearTimeout(debounceTimer);

    if (term === '') {
        results.value = [];
        loading.value = false;
        return;
    }

    loading.value = true;
    const seq = ++requestSeq;
    debounceTimer = setTimeout(async () => {
        try {
            const found = await props.search(term);
            if (seq === requestSeq) results.value = found;
        } catch {
            if (seq === requestSeq) results.value = [];
        } finally {
            if (seq === requestSeq) loading.value = false;
        }
    }, 200);
});

// Date buckets, newest first, with empty buckets dropped — mirrors ChatGPT's
// "Today / Yesterday / Previous 7 days …" grouping.
const groups = computed(() => {
    const startOfToday = dayjs().startOf('day');

    const buckets: { key: string; label: string; items: Conversation[] }[] = [
        { key: 'today', label: t('sidebar.search_group_today'), items: [] },
        { key: 'yesterday', label: t('sidebar.search_group_yesterday'), items: [] },
        { key: 'previous_7_days', label: t('sidebar.search_group_previous_7_days'), items: [] },
        { key: 'previous_30_days', label: t('sidebar.search_group_previous_30_days'), items: [] },
        { key: 'older', label: t('sidebar.search_group_older'), items: [] },
    ];

    const sorted = [...displayItems.value].sort((a, b) => (b.timestamp ?? 0) - (a.timestamp ?? 0));

    for (const conversation of sorted) {
        if (!conversation.timestamp) {
            buckets[4].items.push(conversation);
            continue;
        }

        const created = dayjs.unix(conversation.timestamp);
        if (created.isSame(startOfToday, 'day')) buckets[0].items.push(conversation);
        else if (created.isSame(startOfToday.subtract(1, 'day'), 'day')) buckets[1].items.push(conversation);
        else if (created.isAfter(startOfToday.subtract(7, 'day'))) buckets[2].items.push(conversation);
        else if (created.isAfter(startOfToday.subtract(30, 'day'))) buckets[3].items.push(conversation);
        else buckets[4].items.push(conversation);
    }

    return buckets.filter((bucket) => bucket.items.length > 0);
});

// Flat, keyboard-navigable order: index 0 is always "New chat", then the
// conversations in display (grouped) order. Drives arrow-key selection.
const flatItems = computed<({ type: 'new' } | { type: 'conversation'; id: string | number })[]>(() => {
    const items: ({ type: 'new' } | { type: 'conversation'; id: string | number })[] = [{ type: 'new' }];
    for (const group of groups.value) {
        for (const conversation of group.items) {
            items.push({ type: 'conversation', id: conversation.id });
        }
    }
    return items;
});

const flatIndexById = computed(() => {
    const map = new Map<string | number, number>();
    flatItems.value.forEach((item, index) => {
        if (item.type === 'conversation') map.set(item.id, index);
    });
    return map;
});

watch(activeIndex, (index) => {
    nextTick(() => document.getElementById(`chat-search-item-${index}`)?.scrollIntoView({ block: 'nearest' }));
});

function move(delta: number) {
    const count = flatItems.value.length;
    activeIndex.value = (activeIndex.value + delta + count) % count;
}

function selectActive() {
    const item = flatItems.value[activeIndex.value];
    if (!item) return;
    if (item.type === 'new') chooseNew();
    else choose(item.id);
}

function choose(id: string | number) {
    emit('select', id);
    isOpen.value = false;
}

function chooseNew() {
    emit('new-chat');
    isOpen.value = false;
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        selectActive();
    }
}

const itemClass = (active: boolean) => [
    'flex w-full cursor-pointer items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
    active ? 'bg-accent text-foreground' : 'text-foreground/80',
];
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="top-[12%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl">
            <DialogTitle class="sr-only">{{ t('sidebar.search_chats') }}</DialogTitle>
            <DialogDescription class="sr-only">{{ t('sidebar.search_placeholder') }}</DialogDescription>

            <!-- Search field -->
            <div class="flex items-center gap-3 border-b px-4 py-3.5 pe-12">
                <Search class="text-muted-foreground h-5 w-5 shrink-0" />
                <input
                    ref="searchInput"
                    v-model="query"
                    type="text"
                    :placeholder="t('sidebar.search_placeholder')"
                    class="text-foreground placeholder:text-muted-foreground flex-1 border-0 bg-transparent text-base outline-none focus:ring-0"
                    @keydown="onKeydown"
                />
                <Loader2 v-if="loading" class="text-muted-foreground h-4 w-4 shrink-0 animate-spin" />
            </div>

            <ScrollArea class="max-h-[60vh]">
                <div class="p-2">
                    <!-- New chat — always first, always selectable. -->
                    <button
                        id="chat-search-item-0"
                        type="button"
                        :class="itemClass(activeIndex === 0)"
                        @click="chooseNew"
                        @mouseenter="activeIndex = 0"
                    >
                        <Edit class="h-5 w-5 shrink-0" />
                        <span>{{ t('sidebar.new_chat') }}</span>
                    </button>

                    <!-- No matches for the current query -->
                    <div v-if="hasQuery && !loading && displayItems.length === 0" class="text-muted-foreground/70 px-3 py-8 text-center text-sm">
                        {{ t('sidebar.search_no_results') }}
                    </div>

                    <!-- Grouped conversations (recents when empty, hits when searching) -->
                    <template v-for="group in groups" :key="group.key">
                        <div class="text-muted-foreground px-3 pt-4 pb-1 text-xs font-medium">{{ group.label }}</div>
                        <button
                            v-for="conversation in group.items"
                            :id="`chat-search-item-${flatIndexById.get(conversation.id)}`"
                            :key="conversation.id"
                            type="button"
                            :class="itemClass(flatIndexById.get(conversation.id) === activeIndex)"
                            @click="choose(conversation.id)"
                            @mouseenter="activeIndex = flatIndexById.get(conversation.id) ?? activeIndex"
                        >
                            <MessageSquare class="text-muted-foreground h-4 w-4 shrink-0" />
                            <span class="flex-1 truncate text-start">{{ conversation.title }}</span>
                        </button>
                    </template>
                </div>
            </ScrollArea>
        </DialogContent>
    </Dialog>
</template>
