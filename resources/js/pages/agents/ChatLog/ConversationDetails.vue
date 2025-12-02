<script lang="ts" setup>
import { Badge } from '@/components/ui/badge';
import { MessageSquare } from 'lucide-vue-next';
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
</script>

<template>
  <div v-if="!currentConversationId" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
    <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
    <p class="text-sm">Select a conversation</p>
  </div>
  <div v-else-if="currentConversationData" class="p-5">
    <div class="rounded-lg border bg-card">
      <table class="w-full text-sm">
        <tbody class="divide-y">
          <!-- Messages -->
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium w-1/3">Messages</td>
            <td class="px-4 py-3">{{ totalMessages }}</td>
          </tr>
          
          <!-- Last Activity -->
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium">Last Activity</td>
            <td class="px-4 py-3">{{ formatTimeAgo(currentConversationData.last_at) }}</td>
          </tr>
          
          <!-- Topic -->
          <tr v-if="currentConversationData.topic">
            <td class="px-4 py-3 text-muted-foreground font-medium">Topic</td>
            <td class="px-4 py-3">{{ currentConversationData.topic }}</td>
          </tr>
          
          <!-- Sentiment -->
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium">Sentiment</td>
            <td class="px-4 py-3">
              <template v-if="currentConversationData.sentiment">
                <Badge :class="getSentimentBadgeColor(currentConversationData.sentiment)">
                  {{ getSentimentDisplayName(currentConversationData.sentiment) }}
                </Badge>
              </template>
              <span v-else class="text-muted-foreground italic text-sm">Waiting...</span>
            </td>
          </tr>
          
          <!-- Tone -->
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium">Tone</td>
            <td class="px-4 py-3">
              <template v-if="currentConversationData.tone">
                <Badge :class="getToneBadgeColor(currentConversationData.tone)">
                  {{ getToneEmoji(currentConversationData.tone) }}
                  {{ getToneDisplayName(currentConversationData.tone) }}
                </Badge>
              </template>
              <span v-else class="text-muted-foreground italic text-sm">Waiting...</span>
            </td>
          </tr>
          
          <!-- Country -->
          <tr v-if="currentConversationData.country">
            <td class="px-4 py-3 text-muted-foreground font-medium">Country</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <img 
                  v-if="getFlagPath(currentConversationData.country)"
                  :src="getFlagPath(currentConversationData.country)!" 
                  :alt="currentConversationData.country_name || currentConversationData.country"
                  class="w-5 h-4 rounded-sm object-cover"
                />
                <span>{{ currentConversationData.country_name || currentConversationData.country.toUpperCase() }}</span>
              </div>
            </td>
          </tr>
          
          <!-- City -->
          <tr v-if="currentConversationData.city">
            <td class="px-4 py-3 text-muted-foreground font-medium">City</td>
            <td class="px-4 py-3">{{ currentConversationData.city }}</td>
          </tr>
          
          <!-- Device -->
          <tr v-if="currentConversationData.device_type">
            <td class="px-4 py-3 text-muted-foreground font-medium">Device</td>
            <td class="px-4 py-3 capitalize">{{ currentConversationData.device_type }}</td>
          </tr>
          
          <!-- Browser -->
          <tr v-if="currentConversationData.browser">
            <td class="px-4 py-3 text-muted-foreground font-medium">Browser</td>
            <td class="px-4 py-3">{{ currentConversationData.browser }}</td>
          </tr>
          
          <!-- OS -->
          <tr v-if="currentConversationData.os">
            <td class="px-4 py-3 text-muted-foreground font-medium">Operating System</td>
            <td class="px-4 py-3">{{ currentConversationData.os }}</td>
          </tr>
          
          <!-- Page URL -->
          <tr v-if="currentConversationData.page_url">
            <td class="px-4 py-3 text-muted-foreground font-medium">Page URL</td>
            <td class="px-4 py-3">
              <a 
                :href="currentConversationData.page_url" 
                target="_blank" 
                class="text-blue-600 dark:text-blue-400 hover:underline break-all"
              >
                {{ currentConversationData.page_url }}
              </a>
            </td>
          </tr>
          
          <!-- Referrer -->
          <tr v-if="currentConversationData.referrer_url">
            <td class="px-4 py-3 text-muted-foreground font-medium">Referrer</td>
            <td class="px-4 py-3">
              <a 
                :href="currentConversationData.referrer_url" 
                target="_blank" 
                class="text-blue-600 dark:text-blue-400 hover:underline break-all"
              >
                {{ currentConversationData.referrer_url }}
              </a>
            </td>
          </tr>
          
          <!-- Locale -->
          <tr v-if="currentConversationData.locale">
            <td class="px-4 py-3 text-muted-foreground font-medium">Locale</td>
            <td class="px-4 py-3">{{ currentConversationData.locale }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

