<script lang="ts" setup>
import { computed, watch, onMounted } from 'vue';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { LoaderCircle, MessageSquare, Activity, TrendingUp, TrendingDown, HelpCircle } from 'lucide-vue-next';
import { Line } from 'vue-chartjs';
import { 
  Chart as ChartJS, 
  CategoryScale, 
  LinearScale, 
  PointElement, 
  LineElement,
  Title, 
  Tooltip as ChartTooltip, 
  Legend,
  Filler,
  type ChartOptions
} from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { useChatLog } from './useChatLog';

// Register Chart.js components
ChartJS.register(
  CategoryScale, LinearScale, PointElement, LineElement, 
  Title, ChartTooltip, Legend, Filler, ChartDataLabels
);

const {
  currentConversationId,
  activeTab,
  pulseData,
  isLoadingPulse,
  pulseError,
  fetchPulseData,
  getToneDisplayName,
  getToneEmoji,
  getToneBadgeColor,
  getSentimentBadgeColor,
  getPulsePointColor,
} = useChatLog();

// Load pulse data when tab becomes active
watch(() => activeTab.value, (newTab) => {
  if (newTab === 'pulse' && currentConversationId.value && !pulseData.value) {
    fetchPulseData();
  }
});

// Reset and reload when conversation changes
watch(() => currentConversationId.value, () => {
  if (activeTab.value === 'pulse' && currentConversationId.value) {
    fetchPulseData();
  }
});

// Load on mount if already on pulse tab
onMounted(() => {
  if (activeTab.value === 'pulse' && currentConversationId.value && !pulseData.value) {
    fetchPulseData();
  }
});

function capitalize(str: string): string {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
}

// Chart data
const pulseChartData = computed(() => {
  if (!pulseData.value?.messages?.length) return null;
  const messages = pulseData.value.messages;
  
  return {
    labels: messages.map((_: any, i: number) => i + 1),
    datasets: [{
      label: 'Mood',
      data: messages.map((m: any) => m.sentiment_score),
      borderColor: 'rgb(99, 102, 241)',
      backgroundColor: 'transparent',
      fill: false,
      tension: 0,
      pointRadius: 6,
      pointHoverRadius: 8,
      pointBackgroundColor: messages.map((m: any) => getPulsePointColor(m.sentiment_score)),
      pointBorderColor: '#fff',
      pointBorderWidth: 2,
      borderWidth: 2,
    }],
  };
});

// Chart options
const pulseChartOptions = computed<ChartOptions<'line'>>(() => ({
  responsive: true,
  maintainAspectRatio: false,
  layout: { padding: { top: 10, right: 20 } },
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      padding: 12,
      cornerRadius: 8,
      displayColors: false,
      callbacks: {
        title: (items) => {
          const index = items[0]?.dataIndex;
          if (index !== undefined && pulseData.value?.messages[index]) {
            return `Message #${index + 1}`;
          }
          return '';
        },
        label: (item) => {
          const index = item.dataIndex;
          if (index !== undefined && pulseData.value?.messages[index]) {
            const msg = pulseData.value.messages[index];
            const lines = [];
            if (msg.tone) lines.push(`Tone: ${msg.tone}`);
            if (msg.sentiment) lines.push(`Sentiment: ${msg.sentiment}`);
            if (msg.content_preview) lines.push(`"${msg.content_preview}"`);
            return lines;
          }
          return '';
        },
      },
    },
    datalabels: { display: false },
  },
  scales: {
    x: { display: false },
    y: {
      min: -1.5,
      max: 1.5,
      grid: { color: 'rgba(156, 163, 175, 0.1)' },
      border: { display: false },
      ticks: {
        color: 'rgb(156, 163, 175)',
        font: { size: 11 },
        callback: (value) => {
          if (value === 1) return '😊 Good';
          if (value === 0) return '😐 Neutral';
          if (value === -1) return '😤 Bad';
          return '';
        },
        stepSize: 1,
      },
    },
  },
  interaction: { intersect: false, mode: 'index' },
}));
</script>

<template>
  <!-- No Conversation Selected -->
  <div v-if="!currentConversationId" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
    <Activity class="h-10 w-10 mb-3 opacity-30" />
    <p class="text-sm">Select a conversation</p>
  </div>

  <!-- Loading State -->
  <div v-else-if="isLoadingPulse" class="flex items-center justify-center py-20">
    <LoaderCircle class="h-8 w-8 animate-spin text-muted-foreground" />
  </div>

  <!-- Error State -->
  <div v-else-if="pulseError" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
    <p class="text-sm">{{ pulseError }}</p>
  </div>

  <!-- No Data State -->
  <div v-else-if="!pulseData?.messages?.length" class="flex flex-col items-center justify-center py-20 text-muted-foreground">
    <MessageSquare class="h-10 w-10 mb-3 opacity-30" />
    <p class="text-sm">No user messages to analyze</p>
    <p class="text-xs mt-1">The conversation needs user messages with sentiment data</p>
  </div>

  <!-- Pulse Content -->
  <div v-else class="p-5 space-y-5">
    <!-- Summary Table -->
    <div class="rounded-lg border bg-card">
      <table class="w-full text-sm">
        <tbody class="divide-y">
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium w-1/3">
              <div class="flex items-center gap-1.5">
                Trend
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <HelpCircle class="h-3.5 w-3.5 text-muted-foreground/50 cursor-help" />
                    </TooltipTrigger>
                    <TooltipContent side="right" class="max-w-[240px]">
                      <p class="text-xs">Compares the user's mood from the <strong>first</strong> message to the <strong>last</strong> message.</p>
                      <ul class="text-xs mt-1.5 space-y-0.5">
                        <li><span class="text-green-500">↗ Improving</span> — ended happier</li>
                        <li><span class="text-red-500">↘ Declining</span> — ended more frustrated</li>
                        <li>Stable — mood stayed similar</li>
                      </ul>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>
              </div>
            </td>
            <td class="px-4 py-3">
              <span 
                class="capitalize"
                :class="{
                  'text-green-600 dark:text-green-400': pulseData.summary.trend === 'improving',
                  'text-red-600 dark:text-red-400': pulseData.summary.trend === 'declining'
                }"
              >
                <template v-if="pulseData.summary.trend === 'improving'">↗ </template>
                <template v-else-if="pulseData.summary.trend === 'declining'">↘ </template>
                {{ pulseData.summary.trend }}
              </span>
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium">
              <div class="flex items-center gap-1.5">
                Resolution
                <TooltipProvider>
                  <Tooltip>
                    <TooltipTrigger as-child>
                      <HelpCircle class="h-3.5 w-3.5 text-muted-foreground/50 cursor-help" />
                    </TooltipTrigger>
                    <TooltipContent side="right" class="max-w-[240px]">
                      <p class="text-xs">Checks if a frustrated user was successfully helped.</p>
                      <ul class="text-xs mt-1.5 space-y-0.5">
                        <li><span class="text-green-500">✓ Resolved</span> — started negative, ended positive/neutral</li>
                        <li>Ongoing — issue not yet resolved or user wasn't frustrated</li>
                      </ul>
                    </TooltipContent>
                  </Tooltip>
                </TooltipProvider>
              </div>
            </td>
            <td class="px-4 py-3">
              <span v-if="pulseData.summary.resolution" class="text-green-600 dark:text-green-400">✓ Resolved</span>
              <span v-else class="text-muted-foreground">Ongoing</span>
            </td>
          </tr>
          <tr>
            <td class="px-4 py-3 text-muted-foreground font-medium">User Messages</td>
            <td class="px-4 py-3">{{ pulseData.conversation.user_messages }}</td>
          </tr>
          <tr v-if="pulseData.summary.started_as?.tone || pulseData.summary.started_as?.sentiment">
            <td class="px-4 py-3 text-muted-foreground font-medium">Started As</td>
            <td class="px-4 py-3 capitalize">
              {{ pulseData.summary.started_as?.tone || pulseData.summary.started_as?.sentiment || '—' }}
            </td>
          </tr>
          <tr v-if="pulseData.summary.ended_as?.tone || pulseData.summary.ended_as?.sentiment">
            <td class="px-4 py-3 text-muted-foreground font-medium">Ended As</td>
            <td class="px-4 py-3 capitalize">
              {{ pulseData.summary.ended_as?.tone || pulseData.summary.ended_as?.sentiment || '—' }}
            </td>
          </tr>
          <tr v-if="pulseData.summary.peak_negative">
            <td class="px-4 py-3 text-muted-foreground font-medium">Peak Negative</td>
            <td class="px-4 py-3 capitalize">
              {{ pulseData.summary.peak_negative.tone || pulseData.summary.peak_negative.sentiment }}
              <span class="text-muted-foreground"> · Message #{{ pulseData.summary.peak_negative.index }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Chart -->
    <div class="rounded-lg border bg-card p-4">
      <div class="text-sm font-medium mb-4">Sentiment Timeline</div>
      <div class="h-[240px]">
        <Line v-if="pulseChartData" :data="pulseChartData" :options="pulseChartOptions" />
      </div>
      <!-- Legend -->
      <div class="flex items-center justify-center gap-6 mt-4 pt-4 border-t text-xs text-muted-foreground">
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-green-500"></span>
          <span>😊 Good</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
          <span>😐 Neutral</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="w-3 h-3 rounded-full bg-red-500"></span>
          <span>😤 Bad</span>
        </div>
      </div>
    </div>

    <!-- Message Timeline -->
    <div class="rounded-lg border bg-card">
      <div class="px-4 py-3 border-b">
        <span class="text-sm font-medium">Message Details</span>
      </div>
      <table class="w-full text-sm">
        <tbody class="divide-y">
          <tr v-for="msg in pulseData.messages" :key="msg.index">
            <td class="px-4 py-3 text-muted-foreground font-medium w-12 text-center">{{ msg.index }}</td>
            <td class="px-4 py-3 truncate max-w-[200px]">{{ msg.content_preview }}</td>
            <td class="px-4 py-3 capitalize text-right">
              <template v-if="msg.tone || msg.sentiment">{{ msg.tone || msg.sentiment }}</template>
              <span v-else class="text-muted-foreground italic">Waiting...</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

