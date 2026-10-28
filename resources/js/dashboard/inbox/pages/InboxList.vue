<script lang="ts" setup>
/**
 * The Inbox's left column: everyone chatting right now, with a search box
 * and three filters (all, waiting for a human, held by me). Each row shows
 * who, with which agent, the last message and where the chat stands.
 */
import type { InboxConversation } from '@/dashboard/api/inbox';
import { Avatar, AvatarFallback } from '@/shared/ui/avatar';
import { Badge } from '@/shared/ui/badge';
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { ScrollArea } from '@/shared/ui/scroll-area';
import { Skeleton } from '@/shared/ui/skeleton';
import { getInitials } from '@/shared/composables/useInitials';
import { useTimeAgo } from '@/dashboard/composables/useTimeAgo';
import { getFlagPath } from '@/dashboard/agents/pages/ChatLog/chatLogFormat';
import { getSourceIcon } from '@/dashboard/agents/pages/ChatLog/sourceMeta';
import { Inbox as InboxIcon, RefreshCw, Search } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { injectInbox, type InboxFilter } from './useInbox';

const { t } = useI18n();
const { formatTimeAgo } = useTimeAgo();

const { visibleConversations, conversations, isLoadingList, listFailed, filter, search, waitingCount, mineCount, selectedId, select, loadList } =
    injectInbox();

const filters = computed<{ value: InboxFilter; label: string }[]>(() => [
    { value: 'all', label: t('inbox.filters.all') },
    { value: 'waiting', label: t('inbox.filters.waiting') },
    { value: 'mine', label: t('inbox.filters.mine') },
]);

const emptyTitle = computed(() => {
    if (search.value.trim()) return t('inbox.empty.no_match');
    if (filter.value === 'waiting') return t('inbox.empty.waiting');
    if (filter.value === 'mine') return t('inbox.empty.mine');

    return t('inbox.empty.all');
});

function filterCount(value: InboxFilter): number {
    if (value === 'waiting') return waitingCount.value;
    if (value === 'mine') return mineCount.value;

    return conversations.value.length;
}

function displayName(row: InboxConversation): string {
    return row.visitor_name || t('inbox.anonymous_visitor');
}

function lastMessageLine(row: InboxConversation): string {
    if (!row.last_message) return t('inbox.no_messages');
    if (row.last_message_role === 'agent') return t('inbox.last_message_you', { message: row.last_message });

    return row.last_message;
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="space-y-3 border-b p-3">
            <div class="flex items-center justify-between gap-2">
                <h1 class="text-lg font-semibold">{{ t('inbox.title') }}</h1>
                <Button variant="ghost" size="icon" class="h-8 w-8" :aria-label="t('inbox.refresh')" @click="loadList">
                    <RefreshCw class="h-4 w-4" />
                </Button>
            </div>

            <div class="relative">
                <Search class="text-muted-foreground pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="search" :placeholder="t('inbox.search_placeholder')" class="h-9 ps-8" />
            </div>

            <div class="flex gap-1" role="tablist">
                <button
                    v-for="option in filters"
                    :key="option.value"
                    type="button"
                    role="tab"
                    :aria-selected="filter === option.value"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors"
                    :class="filter === option.value ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:text-foreground'"
                    @click="filter = option.value"
                >
                    {{ option.label }}
                    <span
                        v-if="filterCount(option.value) > 0"
                        class="rounded-full px-1.5 text-[10px] leading-4"
                        :class="
                            option.value === 'waiting' && filter !== option.value
                                ? 'bg-red-500 text-white'
                                : filter === option.value
                                  ? 'bg-primary-foreground/20'
                                  : 'bg-background'
                        "
                    >
                        {{ filterCount(option.value) }}
                    </span>
                </button>
            </div>
        </div>

        <ScrollArea class="h-0 flex-1">
            <div v-if="isLoadingList" class="space-y-1 p-2">
                <div v-for="n in 5" :key="n" class="flex items-center gap-3 p-2">
                    <Skeleton class="h-10 w-10 rounded-full" />
                    <div class="flex-1 space-y-2">
                        <Skeleton class="h-3 w-1/2" />
                        <Skeleton class="h-3 w-4/5" />
                    </div>
                </div>
            </div>

            <div v-else-if="listFailed && conversations.length === 0" class="text-muted-foreground p-6 text-center text-sm">
                {{ t('inbox.load_failed') }}
            </div>

            <div v-else-if="visibleConversations.length === 0" class="text-muted-foreground flex flex-col items-center px-6 py-16 text-center">
                <InboxIcon class="mb-3 h-10 w-10 opacity-30" />
                <p class="text-foreground text-sm font-medium">{{ emptyTitle }}</p>
                <p v-if="!search.trim() && filter === 'all'" class="mt-1 text-xs">{{ t('inbox.empty.all_hint') }}</p>
            </div>

            <ul v-else class="p-1.5">
                <li v-for="row in visibleConversations" :key="row.conversation_id">
                    <button
                        type="button"
                        class="flex w-full items-start gap-3 rounded-lg p-2.5 text-start transition-colors"
                        :class="selectedId === row.conversation_id ? 'bg-accent' : 'hover:bg-muted/60'"
                        @click="select(row.conversation_id)"
                    >
                        <div class="relative shrink-0">
                            <Avatar class="h-10 w-10">
                                <AvatarFallback class="text-xs">{{ getInitials(displayName(row)) }}</AvatarFallback>
                            </Avatar>
                            <span
                                v-if="row.is_online"
                                class="ring-background absolute end-0 bottom-0 h-3 w-3 rounded-full bg-green-500 ring-2"
                                :aria-label="t('inbox.online')"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <img
                                    v-if="getFlagPath(row.country)"
                                    :src="getFlagPath(row.country) ?? ''"
                                    alt=""
                                    class="h-3 w-4 shrink-0 rounded-sm"
                                />
                                <span class="truncate text-sm font-medium">{{ displayName(row) }}</span>
                                <span class="text-muted-foreground ms-auto shrink-0 text-[11px]">{{ formatTimeAgo(row.last_activity_at) }}</span>
                            </div>
                            <p class="text-muted-foreground mt-0.5 truncate text-xs">{{ lastMessageLine(row) }}</p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <span
                                    class="bg-muted text-muted-foreground inline-flex max-w-[10rem] items-center gap-1 rounded px-1.5 py-0.5 text-[10px]"
                                >
                                    <component :is="getSourceIcon(row.source)" v-if="getSourceIcon(row.source)" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ row.agent.name }}</span>
                                </span>
                                <Badge v-if="row.handoff_status === 'pending'" class="bg-red-500 px-1.5 py-0 text-[10px] hover:bg-red-500">
                                    {{ t('inbox.status.waiting') }}
                                </Badge>
                                <Badge v-else-if="row.held_by_me" class="bg-amber-500 px-1.5 py-0 text-[10px] hover:bg-amber-500">
                                    {{ t('inbox.status.mine') }}
                                </Badge>
                                <Badge v-else-if="row.holder_name" variant="secondary" class="px-1.5 py-0 text-[10px]">
                                    {{ t('inbox.status.held_by', { name: row.holder_name }) }}
                                </Badge>
                            </div>
                        </div>
                    </button>
                </li>
            </ul>
        </ScrollArea>
    </div>
</template>
