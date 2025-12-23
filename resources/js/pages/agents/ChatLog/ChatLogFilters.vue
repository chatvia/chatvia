<script lang="ts" setup>
import { Button } from '@/components/ui/button';
import DateRangePicker from '@/components/ui/DateRangePicker.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { Filter, ThumbsDown, ThumbsUp } from 'lucide-vue-next';
import { computed } from 'vue';
import { useChatLog } from './useChatLog';

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
                    Filter by
                </DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <!-- Two Column Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- Date Range -->
                    <div class="space-y-2">
                        <Label class="text-sm font-medium">Date range</Label>
                        <DateRangePicker v-model="dateRange" class="w-full" />
                    </div>

                    <!-- Confidence Score -->
                    <div class="space-y-2">
                        <Label for="confidence-filter" class="text-sm font-medium">Confidence score</Label>
                        <Select v-model="selectedConfidenceScore">
                            <SelectTrigger id="confidence-filter" class="w-full">
                                <SelectValue placeholder="Select score" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All scores</SelectItem>
                                <SelectItem value="high">High (≥80%)</SelectItem>
                                <SelectItem value="medium">Medium (65-80%)</SelectItem>
                                <SelectItem value="low">Low (&lt;65%)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Source -->
                    <div class="space-y-2">
                        <Label for="source-filter" class="text-sm font-medium">Source</Label>
                        <Select v-model="selectedSource">
                            <SelectTrigger id="source-filter" class="w-full">
                                <SelectValue placeholder="Select source" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sources</SelectItem>
                                <SelectItem value="widget">Widget</SelectItem>
                                <SelectItem value="playground">Playground</SelectItem>
                                <SelectItem value="api">API</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Feedback -->
                    <div class="space-y-2">
                        <Label for="feedback-filter" class="text-sm font-medium">Feedback</Label>
                        <Select v-model="selectedFeedback">
                            <SelectTrigger id="feedback-filter" class="w-full">
                                <SelectValue placeholder="Select feedback" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All feedback</SelectItem>
                                <SelectItem value="positive">
                                    <div class="flex items-center gap-2">
                                        <ThumbsUp class="h-4 w-4 text-green-600" />
                                        Thumbs Up
                                    </div>
                                </SelectItem>
                                <SelectItem value="negative">
                                    <div class="flex items-center gap-2">
                                        <ThumbsDown class="h-4 w-4 text-red-600" />
                                        Thumbs Down
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Country -->
                    <div class="space-y-2">
                        <Label for="country-filter" class="text-sm font-medium">Country</Label>
                        <Select v-model="selectedCountry">
                            <SelectTrigger id="country-filter" class="w-full">
                                <SelectValue placeholder="Select country" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All countries</SelectItem>
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
                        <Label for="topic-filter" class="text-sm font-medium" :class="{ 'opacity-50': !hasTopicsAccess }">Topic</Label>
                        <Select v-model="selectedTopic" :disabled="!hasTopicsAccess">
                            <SelectTrigger id="topic-filter" class="w-full" :disabled="!hasTopicsAccess">
                                <SelectValue :placeholder="hasTopicsAccess ? 'Select topic' : 'Upgrade to unlock'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All topics</SelectItem>
                                <SelectItem v-for="topic in topics" :key="topic.id" :value="String(topic.id)">
                                    {{ topic.topic }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Sentiment -->
                    <div class="space-y-2">
                        <Label for="sentiment-filter" class="text-sm font-medium" :class="{ 'opacity-50': !hasSentimentAccess }">Sentiment</Label>
                        <Select v-model="selectedSentiment" :disabled="!hasSentimentAccess">
                            <SelectTrigger id="sentiment-filter" class="w-full" :disabled="!hasSentimentAccess">
                                <SelectValue :placeholder="hasSentimentAccess ? 'Select sentiment' : 'Upgrade to unlock'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sentiments</SelectItem>
                                <SelectItem value="positive">Positive</SelectItem>
                                <SelectItem value="neutral">Neutral</SelectItem>
                                <SelectItem value="negative">Negative</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <!-- Tone -->
                    <div class="space-y-2">
                        <Label for="tone-filter" class="text-sm font-medium" :class="{ 'opacity-50': !hasToneAccess }">Tone</Label>
                        <Select v-model="selectedTone" :disabled="!hasToneAccess">
                            <SelectTrigger id="tone-filter" class="w-full" :disabled="!hasToneAccess">
                                <SelectValue :placeholder="hasToneAccess ? 'Select tone' : 'Upgrade to unlock'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All tones</SelectItem>
                                <SelectItem value="neutral">😐 Neutral</SelectItem>
                                <SelectItem value="frustrated">😤 Frustrated</SelectItem>
                                <SelectItem value="urgent">⚡ Urgent</SelectItem>
                                <SelectItem value="confused">😕 Confused</SelectItem>
                                <SelectItem value="satisfied">😊 Satisfied</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex gap-2 sm:gap-2">
                <Button variant="ghost" @click="clearAllFilters" :disabled="!hasActiveFilters" class="text-destructive hover:text-destructive">
                    Clear all
                </Button>
                <Button @click="applyFilters"> Close </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
