<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { Calendar, Circle, Download, Filter, Globe, LoaderCircle, RefreshCcw, ThumbsDown, ThumbsUp, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { getSourceIcon } from './sourceMeta';
import { useChatLog } from './useChatLog';

const { t } = useI18n();

const { hasCapability } = useCapabilityGuard();
const hasTopicsAccess = computed(() => hasCapability('topics'));

const {
    agent: _agent,
    conversations,
    currentConversationId,
    leadConversationIds: _leadConversationIds,
    isLoadingConversations,
    totalConversations,
    currentConversationsPage,
    hasActiveFilters,
    activeFilterCount,
    showFiltersDialog,
    selectedTopic,
    selectedSentiment,
    selectedTone,
    selectedCountry,
    selectedConfidenceScore,
    selectedFeedback,
    selectedSource,
    dateRange,
    onlineOnly,
    fetchConversations,
    selectConversation,
    formatTimeAgo,
    getFlagPath,
    getTopicName,
    getCountryName,
    getSentimentDisplayName,
    getSentimentBadgeColor,
    getToneDisplayName,
    getToneEmoji,
    getToneBadgeColor,
    getConfidenceScoreDisplayName,
    getFeedbackDisplayName,
    getSourceDisplayName,
    formatDateRangeDisplay,
} = useChatLog();

function toggleOnlineOnly() {
    onlineOnly.value = !onlineOnly.value;
    fetchConversations(1);
}

// Relative-time string for the conversation's per-plan retention horizon.
// Returns null when the date is missing or already past so we can hide the
// row entirely; the prune command will sweep the row on its next run anyway.
function formatExpiresIn(iso: string | null | undefined): string | null {
    if (!iso) return null;
    const ms = new Date(iso).getTime() - Date.now();
    if (ms <= 0) return null;
    const days = Math.ceil(ms / 86_400_000);
    if (days <= 1) return t('agents.chatlog.sidebar.expires_today');
    if (days < 30) return t('agents.chatlog.sidebar.expires_in_days', { count: days });
    const months = Math.round(days / 30);
    return months === 1 ? t('agents.chatlog.sidebar.expires_in_one_month') : t('agents.chatlog.sidebar.expires_in_months', { count: months });
}

const isRefreshingConversations = ref(false);

async function refreshConversations() {
    isRefreshingConversations.value = true;
    try {
        await fetchConversations(currentConversationsPage.value, true);
    } finally {
        isRefreshingConversations.value = false;
    }
}

function clearFilter(filter: string) {
    switch (filter) {
        case 'topic':
            selectedTopic.value = 'all';
            break;
        case 'sentiment':
            selectedSentiment.value = 'all';
            break;
        case 'tone':
            selectedTone.value = 'all';
            break;
        case 'country':
            selectedCountry.value = 'all';
            break;
        case 'confidence':
            selectedConfidenceScore.value = 'all';
            break;
        case 'feedback':
            selectedFeedback.value = 'all';
            break;
        case 'source':
            selectedSource.value = 'all';
            break;
        case 'dateRange':
            dateRange.value = undefined;
            break;
    }
    fetchConversations(1);
}

// Export functions (placeholders)
function exportAsJSON() {
    console.log('Export JSON');
}
function exportAsCSV() {
    console.log('Export CSV');
}
function exportAsPDF() {
    console.log('Export PDF');
}
</script>

<script lang="ts">
import { ref } from 'vue';
</script>

<template>
    <div class="bg-muted/30 flex h-full w-[300px] flex-shrink-0 flex-col border-e">
        <!-- Header Section -->
        <div class="border-border/50 bg-muted/30 flex-shrink-0 border-b px-4 pt-4 pb-3">
            <div class="mb-3 flex items-center justify-between">
                <div class="flex items-center gap-1">
                    <!-- Online Only Toggle -->
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="toggleOnlineOnly"
                                    class="h-8 gap-1.5 px-2.5"
                                    :class="
                                        onlineOnly
                                            ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400 dark:hover:bg-green-900/50'
                                            : ''
                                    "
                                >
                                    <Circle class="h-2.5 w-2.5 fill-current" :class="onlineOnly ? 'text-green-500' : 'text-muted-foreground'" />
                                    <span class="text-xs font-medium">{{ t('agents.chatlog.sidebar.online') }}</span>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom">
                                <p>{{ t('agents.chatlog.sidebar.show_online_only') }}</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
                <div class="flex items-center gap-1">
                    <!-- Filters Button -->
                    <Button
                        variant="ghost"
                        size="icon"
                        class="relative h-8 w-8 cursor-pointer"
                        :class="{ 'text-primary': hasActiveFilters }"
                        @click="showFiltersDialog = true"
                    >
                        <Filter class="h-4 w-4" />
                        <span
                            v-if="hasActiveFilters"
                            class="bg-primary text-primary-foreground absolute -end-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full text-[10px] font-medium"
                        >
                            {{ activeFilterCount }}
                        </span>
                    </Button>

                    <!-- Refresh Conversations Button -->
                    <Button variant="ghost" size="icon" class="h-8 w-8" @click="refreshConversations" :disabled="isRefreshingConversations">
                        <RefreshCcw class="h-4 w-4" :class="{ 'animate-spin': isRefreshingConversations }" />
                    </Button>

                    <!-- Export Dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="default" size="icon" class="h-8 w-8">
                                <Download class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="exportAsJSON">{{ t('agents.chatlog.sidebar.export_json') }}</DropdownMenuItem>
                            <DropdownMenuItem @click="exportAsCSV">{{ t('agents.chatlog.sidebar.export_csv') }}</DropdownMenuItem>
                            <DropdownMenuItem @click="exportAsPDF">{{ t('agents.chatlog.sidebar.export_pdf') }}</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="mb-3 flex flex-wrap gap-1.5">
                <Badge v-if="dateRange" variant="secondary" class="flex items-center gap-1 px-2 py-1 text-xs">
                    <Calendar class="h-3 w-3" />
                    {{ formatDateRangeDisplay() }}
                    <button @click="clearFilter('dateRange')" class="hover:text-foreground ms-1 cursor-pointer"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge v-if="selectedCountry !== 'all'" variant="secondary" class="flex items-center gap-1 px-2 py-1 text-xs">
                    <Globe class="h-3 w-3" />
                    {{ getCountryName(selectedCountry) }}
                    <button @click="clearFilter('country')" class="hover:text-foreground ms-1 cursor-pointer"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge
                    v-if="selectedConfidenceScore !== 'all'"
                    variant="secondary"
                    class="flex items-center gap-1 px-2 py-1 text-xs"
                    :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': selectedConfidenceScore === 'high',
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300': selectedConfidenceScore === 'medium',
                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': selectedConfidenceScore === 'low',
                    }"
                >
                    {{ getConfidenceScoreDisplayName(selectedConfidenceScore) }}
                    <button @click="clearFilter('confidence')" class="ms-1 cursor-pointer hover:opacity-70"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge
                    v-if="selectedFeedback !== 'all'"
                    variant="secondary"
                    class="flex items-center gap-1 px-2 py-1 text-xs"
                    :class="{
                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300': selectedFeedback === 'positive',
                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300': selectedFeedback === 'negative',
                    }"
                >
                    <ThumbsUp v-if="selectedFeedback === 'positive'" class="h-3 w-3" />
                    <ThumbsDown v-else class="h-3 w-3" />
                    {{ getFeedbackDisplayName(selectedFeedback) }}
                    <button @click="clearFilter('feedback')" class="ms-1 cursor-pointer hover:opacity-70"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge v-if="selectedSource !== 'all'" variant="secondary" class="flex items-center gap-1 px-2 py-1 text-xs">
                    {{ getSourceDisplayName(selectedSource) }}
                    <button @click="clearFilter('source')" class="hover:text-foreground ms-1 cursor-pointer"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge v-if="selectedTopic !== 'all'" variant="secondary" class="flex items-center gap-1 px-2 py-1 text-xs">
                    {{ getTopicName(Number(selectedTopic)) }}
                    <button @click="clearFilter('topic')" class="hover:text-foreground ms-1 cursor-pointer"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge
                    v-if="selectedSentiment !== 'all'"
                    :class="getSentimentBadgeColor(selectedSentiment)"
                    class="flex items-center gap-1 px-2 py-1 text-xs"
                >
                    {{ getSentimentDisplayName(selectedSentiment) }}
                    <button @click="clearFilter('sentiment')" class="ms-1 cursor-pointer hover:opacity-70"><X class="h-3 w-3" /></button>
                </Badge>

                <Badge v-if="selectedTone !== 'all'" :class="getToneBadgeColor(selectedTone)" class="flex items-center gap-1 px-2 py-1 text-xs">
                    {{ getToneEmoji(selectedTone) }} {{ getToneDisplayName(selectedTone) }}
                    <button @click="clearFilter('tone')" class="ms-1 cursor-pointer hover:opacity-70"><X class="h-3 w-3" /></button>
                </Badge>
            </div>

            <div class="text-muted-foreground flex items-center justify-between text-xs">
                <span>{{ t('agents.chatlog.sidebar.conversations_count', { count: totalConversations }) }}</span>
            </div>
        </div>

        <!-- Scrollable Conversations Area -->
        <div class="min-h-0 flex-1 overflow-y-auto">
            <!-- Loading State for Conversations -->
            <div v-if="isLoadingConversations" class="flex items-center justify-center py-12">
                <LoaderCircle class="text-muted-foreground h-8 w-8 animate-spin" />
            </div>

            <!-- Conversations List -->
            <ul v-else class="space-y-0.5 p-2">
                <li v-for="c in conversations?.data" :key="c.conversation_id">
                    <button
                        @click="selectConversation(c.conversation_id, c)"
                        class="group flex w-full cursor-pointer flex-col gap-1.5 rounded-lg px-3 py-2.5 text-start text-sm transition-all duration-200"
                        :class="
                            currentConversationId === c.conversation_id ? 'bg-primary text-primary-foreground shadow-md' : 'hover:bg-background/80'
                        "
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="flex-1 truncate text-[13px] font-medium" :title="c.first_message_excerpt || c.conversation_id">
                                {{ c.first_message_excerpt || c.conversation_id.slice(0, 8) }}
                            </span>
                            <div class="flex flex-shrink-0 items-center gap-1.5">
                                <span
                                    class="text-[10px] tabular-nums"
                                    :class="currentConversationId === c.conversation_id ? 'opacity-80' : 'opacity-50'"
                                >
                                    {{ c.messages_count }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-2 text-[11px]"
                            :class="currentConversationId === c.conversation_id ? 'opacity-70' : 'opacity-50'"
                        >
                            <Tooltip v-if="c.source && getSourceIcon(c.source)">
                                <TooltipTrigger as-child>
                                    <component :is="getSourceIcon(c.source)" class="h-3 w-3 flex-shrink-0" />
                                </TooltipTrigger>
                                <TooltipContent side="top">
                                    {{ getSourceDisplayName(c.source) }}
                                </TooltipContent>
                            </Tooltip>
                            <img
                                v-if="c.country && getFlagPath(c.country)"
                                :src="getFlagPath(c.country)!"
                                :alt="c.country"
                                class="h-3 w-3 rounded-sm object-cover"
                                :title="c.country.toUpperCase()"
                            />
                            <span v-if="hasTopicsAccess && c.topics?.length" class="max-w-[100px] truncate">{{ c.topics.join(', ') }}</span>
                            <span v-if="hasTopicsAccess && c.topics?.length" class="opacity-50">•</span>
                            <span>{{ formatTimeAgo(c.last_at) }}</span>
                        </div>

                        <!-- Per-plan retention horizon. Hidden when expires_at
                             is null (legacy rows) or already in the past — the
                             prune command sweeps those on its next run. -->
                        <div
                            v-if="formatExpiresIn(c.expires_at)"
                            class="flex items-center gap-1 text-[11px]"
                            :class="currentConversationId === c.conversation_id ? 'opacity-60' : 'opacity-40'"
                        >
                            <Calendar class="h-3 w-3 flex-shrink-0" />
                            <span>{{ formatExpiresIn(c.expires_at) }}</span>
                        </div>
                    </button>
                </li>
            </ul>
        </div>

        <!-- Pagination Controls -->
        <div v-if="conversations && conversations.last_page > 1" class="border-border/50 bg-muted/30 flex-shrink-0 border-t p-3">
            <div class="flex items-center justify-between gap-2">
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 text-xs"
                    :disabled="!conversations?.prev_page_url || isLoadingConversations"
                    @click="fetchConversations(conversations.current_page - 1)"
                >
                    <LoaderCircle v-if="isLoadingConversations" class="me-1 h-3 w-3 animate-spin" />
                    {{ t('agents.chatlog.pagination.prev') }}
                </Button>
                <span class="text-muted-foreground text-[10px] tabular-nums">
                    {{ conversations?.current_page }} / {{ conversations?.last_page }}
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-7 text-xs"
                    :disabled="!conversations?.next_page_url || isLoadingConversations"
                    @click="fetchConversations(conversations.current_page + 1)"
                >
                    <LoaderCircle v-if="isLoadingConversations" class="me-1 h-3 w-3 animate-spin" />
                    {{ t('agents.chatlog.pagination.next') }}
                </Button>
            </div>
        </div>
    </div>
</template>
