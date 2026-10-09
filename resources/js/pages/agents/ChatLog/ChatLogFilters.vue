<script lang="ts" setup>
/**
 * Filters dialog of the chat log (date range, confidence, channel, feedback,
 * country, topic, sentiment, tone). Selections live in `useChatLog`; "Apply"
 * reloads the conversation list.
 */
import { Button } from '@/components/ui/button';
import DateRangePicker from '@/components/common/DateRangePicker.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { Filter, ThumbsDown, ThumbsUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useChatLog } from './useChatLog';

const { t } = useI18n();

const {
    showFiltersDialog,
    topics,
    countries,
    selectedTopic,
    selectedSentiment,
    selectedTone,
    selectedCountry,
    selectedConfidenceScore,
    selectedFeedback,
    selectedSource,
    dateRange,
    hasActiveFilters,
    applyFilters,
    clearAllFilters,
    getFlagPath,
} = useChatLog();

const { hasCapability } = useCapabilityGuard();
const hasToneAccess = computed(() => hasCapability('tone'));
const hasSentimentAccess = computed(() => hasCapability('sentiment'));
const hasTopicsAccess = computed(() => hasCapability('topics'));
</script>

<template>
    <Dialog v-model:open="showFiltersDialog">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Filter class="h-5 w-5" />
                    {{ t('agents.chatlog.filters.title') }}
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <!-- Two Column Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Date Range -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium">{{ t('agents.chatlog.filters.date_range') }}</Label>
                        <DateRangePicker v-model="dateRange" class="w-full" />
                    </div>

                    <!-- Confidence Score -->
                    <div class="space-y-2">
                        <Label for="confidence-filter" class="text-sm font-medium">{{ t('agents.chatlog.filters.confidence_score') }}</Label>
                        <Select v-model="selectedConfidenceScore">
                            <SelectTrigger id="confidence-filter" class="w-full">
                                <SelectValue :placeholder="t('agents.chatlog.filters.select_score')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_scores') }}</SelectItem>
                                <SelectItem value="high">{{ t('agents.chatlog.confidence.high') }}</SelectItem>
                                <SelectItem value="medium">{{ t('agents.chatlog.confidence.medium') }}</SelectItem>
                                <SelectItem value="low">{{ t('agents.chatlog.confidence.low') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Source -->
                    <div class="space-y-2">
                        <Label for="source-filter" class="text-sm font-medium">{{ t('agents.chatlog.filters.source') }}</Label>
                        <Select v-model="selectedSource">
                            <SelectTrigger id="source-filter" class="w-full">
                                <SelectValue :placeholder="t('agents.chatlog.filters.select_source')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_sources') }}</SelectItem>
                                <SelectItem value="hosted_chat">{{ t('agents.chatlog.source.hosted_chat') }}</SelectItem>
                                <SelectItem value="widget">{{ t('agents.chatlog.source.widget') }}</SelectItem>
                                <SelectItem value="bar">{{ t('agents.chatlog.source.bar') }}</SelectItem>
                                <SelectItem value="telegram">{{ t('agents.chatlog.source.telegram') }}</SelectItem>
                                <SelectItem value="slack">{{ t('agents.chatlog.source.slack') }}</SelectItem>
                                <SelectItem value="messenger">{{ t('agents.chatlog.source.messenger') }}</SelectItem>
                                <SelectItem value="whatsapp">{{ t('agents.chatlog.source.whatsapp') }}</SelectItem>
                                <SelectItem value="msteams">{{ t('agents.chatlog.source.msteams') }}</SelectItem>
                                <SelectItem value="instagram">{{ t('agents.chatlog.source.instagram') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Feedback -->
                    <div class="space-y-2">
                        <Label for="feedback-filter" class="text-sm font-medium">{{ t('agents.chatlog.filters.feedback') }}</Label>
                        <Select v-model="selectedFeedback">
                            <SelectTrigger id="feedback-filter" class="w-full">
                                <SelectValue :placeholder="t('agents.chatlog.filters.select_feedback')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_feedback') }}</SelectItem>
                                <SelectItem value="positive">
                                    <div class="flex items-center gap-2">
                                        <ThumbsUp class="h-4 w-4 text-green-600" />
                                        {{ t('agents.chatlog.feedback.thumbs_up') }}
                                    </div>
                                </SelectItem>
                                <SelectItem value="negative">
                                    <div class="flex items-center gap-2">
                                        <ThumbsDown class="h-4 w-4 text-red-600" />
                                        {{ t('agents.chatlog.feedback.thumbs_down') }}
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Country -->
                    <div class="space-y-2">
                        <Label for="country-filter" class="text-sm font-medium">{{ t('agents.chatlog.filters.country') }}</Label>
                        <Select v-model="selectedCountry">
                            <SelectTrigger id="country-filter" class="w-full">
                                <SelectValue :placeholder="t('agents.chatlog.filters.select_country')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_countries') }}</SelectItem>
                                <SelectItem v-for="country in countries" :key="country.id" :value="String(country.id)">
                                    <div class="flex items-center gap-2">
                                        <img
                                            v-if="country.code && getFlagPath(country.code)"
                                            :src="getFlagPath(country.code)!"
                                            :alt="country.code"
                                            class="h-3 w-4 rounded-sm object-cover"
                                        />
                                        {{ country.name }}
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Topic -->
                    <div class="space-y-2">
                        <Label for="topic-filter" class="text-sm font-medium" :class="{ 'opacity-50': !hasTopicsAccess }">{{
                            t('agents.chatlog.filters.topic')
                        }}</Label>
                        <Select v-model="selectedTopic" :disabled="!hasTopicsAccess">
                            <SelectTrigger id="topic-filter" class="w-full" :disabled="!hasTopicsAccess">
                                <SelectValue
                                    :placeholder="
                                        hasTopicsAccess ? t('agents.chatlog.filters.select_topic') : t('agents.chatlog.filters.upgrade_to_unlock')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_topics') }}</SelectItem>
                                <SelectItem v-for="topic in topics" :key="topic.id" :value="String(topic.id)">
                                    {{ topic.topic }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Sentiment -->
                    <div class="space-y-2">
                        <Label for="sentiment-filter" class="text-sm font-medium" :class="{ 'opacity-50': !hasSentimentAccess }">{{
                            t('agents.chatlog.filters.sentiment')
                        }}</Label>
                        <Select v-model="selectedSentiment" :disabled="!hasSentimentAccess">
                            <SelectTrigger id="sentiment-filter" class="w-full" :disabled="!hasSentimentAccess">
                                <SelectValue
                                    :placeholder="
                                        hasSentimentAccess
                                            ? t('agents.chatlog.filters.select_sentiment')
                                            : t('agents.chatlog.filters.upgrade_to_unlock')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_sentiments') }}</SelectItem>
                                <SelectItem value="positive">{{ t('agents.chatlog.sentiment.positive') }}</SelectItem>
                                <SelectItem value="neutral">{{ t('agents.chatlog.sentiment.neutral') }}</SelectItem>
                                <SelectItem value="negative">{{ t('agents.chatlog.sentiment.negative') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Tone -->
                    <div class="space-y-2">
                        <Label for="tone-filter" class="text-sm font-medium" :class="{ 'opacity-50': !hasToneAccess }">{{
                            t('agents.chatlog.filters.tone')
                        }}</Label>
                        <Select v-model="selectedTone" :disabled="!hasToneAccess">
                            <SelectTrigger id="tone-filter" class="w-full" :disabled="!hasToneAccess">
                                <SelectValue
                                    :placeholder="
                                        hasToneAccess ? t('agents.chatlog.filters.select_tone') : t('agents.chatlog.filters.upgrade_to_unlock')
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{{ t('agents.chatlog.filters.all_tones') }}</SelectItem>
                                <SelectItem value="neutral">😐 {{ t('agents.chatlog.tone.neutral') }}</SelectItem>
                                <SelectItem value="frustrated">😤 {{ t('agents.chatlog.tone.frustrated') }}</SelectItem>
                                <SelectItem value="urgent">⚡ {{ t('agents.chatlog.tone.urgent') }}</SelectItem>
                                <SelectItem value="confused">😕 {{ t('agents.chatlog.tone.confused') }}</SelectItem>
                                <SelectItem value="satisfied">😊 {{ t('agents.chatlog.tone.satisfied') }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex gap-2 sm:gap-2">
                <Button variant="ghost" @click="clearAllFilters" :disabled="!hasActiveFilters" class="text-destructive hover:text-destructive">
                    {{ t('agents.chatlog.filters.clear_all') }}
                </Button>
                <Button @click="applyFilters"> {{ t('agents.chatlog.filters.close') }} </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
