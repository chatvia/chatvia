<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCapabilityGuard } from '@/composables/useCapabilityGuard';
import { Link } from '@inertiajs/vue3';
import { MessageSquare, Zap } from 'lucide-vue-next';
import { computed } from 'vue';
import { useChatLog } from './useChatLog';

const {
    currentConversationId,
    currentConversationData,
    totalMessages,
    formatTimeAgo,
    getFlagPath,
    getSentimentDisplayName,
    getSentimentBadgeColor,
    getToneDisplayName,
    getToneEmoji,
    getToneBadgeColor,
} = useChatLog();

const { hasCapability } = useCapabilityGuard();
const hasSentimentAccess = computed(() => hasCapability('sentiment'));
const hasToneAccess = computed(() => hasCapability('tone'));
const hasTopicsAccess = computed(() => hasCapability('topics'));
</script>

<template>
    <div v-if="!currentConversationId" class="text-muted-foreground flex flex-col items-center justify-center py-20">
        <MessageSquare class="mb-3 h-10 w-10 opacity-30" />
        <p class="text-sm">Select a conversation</p>
    </div>
    <div v-else-if="currentConversationData" class="p-5">
        <div class="bg-card rounded-lg border">
            <table class="w-full text-sm">
                <tbody class="divide-y">
                    <!-- Messages -->
                    <tr>
                        <td class="text-muted-foreground w-1/3 px-4 py-3 font-medium">Messages</td>
                        <td class="px-4 py-3">{{ totalMessages }}</td>
                    </tr>

                    <!-- Last Activity -->
                    <tr>
                        <td class="text-muted-foreground px-4 py-3 font-medium">Last Activity</td>
                        <td class="px-4 py-3">{{ formatTimeAgo(currentConversationData.last_at) }}</td>
                    </tr>

                    <!-- Topic -->
                    <tr>
                        <td class="text-muted-foreground px-4 py-3 font-medium">Topic</td>
                        <td class="px-4 py-3">
                            <template v-if="!hasTopicsAccess">
                                <Button as-child size="sm" class="h-7 gap-1 text-xs">
                                    <Link :href="route('billing.packages')">
                                        <Zap class="h-3 w-3" />
                                        Upgrade to unlock
                                    </Link>
                                </Button>
                            </template>
                            <template v-else-if="currentConversationData.topic">
                                {{ currentConversationData.topic }}
                            </template>
                            <span v-else class="text-muted-foreground text-sm italic">Waiting...</span>
                        </td>
                    </tr>

                    <!-- Sentiment -->
                    <tr>
                        <td class="text-muted-foreground px-4 py-3 font-medium">Sentiment</td>
                        <td class="px-4 py-3">
                            <template v-if="!hasSentimentAccess">
                                <Button as-child size="sm" class="h-7 gap-1 text-xs">
                                    <Link :href="route('billing.packages')">
                                        <Zap class="h-3 w-3" />
                                        Upgrade to unlock
                                    </Link>
                                </Button>
                            </template>
                            <template v-else-if="currentConversationData.sentiment">
                                <Badge :class="getSentimentBadgeColor(currentConversationData.sentiment)">
                                    {{ getSentimentDisplayName(currentConversationData.sentiment) }}
                                </Badge>
                            </template>
                            <span v-else class="text-muted-foreground text-sm italic">Waiting...</span>
                        </td>
                    </tr>

                    <!-- Tone -->
                    <tr>
                        <td class="text-muted-foreground px-4 py-3 font-medium">Tone</td>
                        <td class="px-4 py-3">
                            <template v-if="!hasToneAccess">
                                <Button as-child size="sm" class="h-7 gap-1 text-xs">
                                    <Link :href="route('billing.packages')">
                                        <Zap class="h-3 w-3" />
                                        Upgrade to unlock
                                    </Link>
                                </Button>
                            </template>
                            <template v-else-if="currentConversationData.tone">
                                <Badge :class="getToneBadgeColor(currentConversationData.tone)">
                                    {{ getToneEmoji(currentConversationData.tone) }}
                                    {{ getToneDisplayName(currentConversationData.tone) }}
                                </Badge>
                            </template>
                            <span v-else class="text-muted-foreground text-sm italic">Waiting...</span>
                        </td>
                    </tr>

                    <!-- Country -->
                    <tr v-if="currentConversationData.country">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Country</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <img
                                    v-if="getFlagPath(currentConversationData.country)"
                                    :src="getFlagPath(currentConversationData.country)!"
                                    :alt="currentConversationData.country_name || currentConversationData.country"
                                    class="h-4 w-5 rounded-sm object-cover"
                                />
                                <span>{{ currentConversationData.country_name || currentConversationData.country.toUpperCase() }}</span>
                            </div>
                        </td>
                    </tr>

                    <!-- City -->
                    <tr v-if="currentConversationData.city">
                        <td class="text-muted-foreground px-4 py-3 font-medium">City</td>
                        <td class="px-4 py-3">{{ currentConversationData.city }}</td>
                    </tr>

                    <!-- Device -->
                    <tr v-if="currentConversationData.device_type">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Device</td>
                        <td class="px-4 py-3 capitalize">{{ currentConversationData.device_type }}</td>
                    </tr>

                    <!-- Browser -->
                    <tr v-if="currentConversationData.browser">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Browser</td>
                        <td class="px-4 py-3">{{ currentConversationData.browser }}</td>
                    </tr>

                    <!-- OS -->
                    <tr v-if="currentConversationData.os">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Operating System</td>
                        <td class="px-4 py-3">{{ currentConversationData.os }}</td>
                    </tr>

                    <!-- Page URL -->
                    <tr v-if="currentConversationData.page_url">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Page URL</td>
                        <td class="px-4 py-3">
                            <a
                                :href="currentConversationData.page_url"
                                target="_blank"
                                class="break-all text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {{ currentConversationData.page_url }}
                            </a>
                        </td>
                    </tr>

                    <!-- Referrer -->
                    <tr v-if="currentConversationData.referrer_url">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Referrer</td>
                        <td class="px-4 py-3">
                            <a
                                :href="currentConversationData.referrer_url"
                                target="_blank"
                                class="break-all text-blue-600 hover:underline dark:text-blue-400"
                            >
                                {{ currentConversationData.referrer_url }}
                            </a>
                        </td>
                    </tr>

                    <!-- Locale -->
                    <tr v-if="currentConversationData.locale">
                        <td class="text-muted-foreground px-4 py-3 font-medium">Locale</td>
                        <td class="px-4 py-3">{{ currentConversationData.locale }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
