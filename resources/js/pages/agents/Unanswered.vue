<script setup lang="ts">
/**
 * "Unanswered": the agent's replies that need a look, newest first. A reply
 * is listed when no knowledge was found for it or a visitor gave it a
 * thumbs-down (with their note). Each row opens the conversation in the chat
 * log or the "Revise answer" dialog, which turns the fix into a Q&A entry
 * (and an eval case). Revised replies are hidden unless asked for.
 */
import { fetchUnansweredQuestions, type UnansweredQuestion, type UnansweredQuestionsPage } from '@/api/analytics';
import { chatLogUrl } from '@/api/chatLog';
import { errorMessage } from '@/api/http';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import AgentLayout from '@/layouts/AgentLayout.vue';
import ReviseAnswerDialog from '@/pages/agents/ChatLog/ReviseAnswerDialog.vue';
import { Link } from '@inertiajs/vue3';
import { CircleHelp, ExternalLink, LoaderCircle, RotateCcw } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

type Filter = Domain.Agent.Enums.UnansweredFilter;

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    chatlogEnabled: boolean;
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
const { hasPermission } = useTeamPermissions();

// Revising writes a Q&A entry, which needs the Q&A training permission.
const canRevise = computed(() => hasPermission('agent.sources.train.qa'));

const filter = ref<Filter>('all');
const includeRevised = ref(false);
const pageNumber = ref(1);
const result = ref<UnansweredQuestionsPage | null>(null);
const isLoading = ref(false);
const loadError = ref<string | null>(null);

const rows = computed<UnansweredQuestion[]>(() => result.value?.data ?? []);

async function load() {
    isLoading.value = true;
    loadError.value = null;
    try {
        result.value = await fetchUnansweredQuestions(props.agent.external_id, {
            filter: filter.value,
            includeRevised: includeRevised.value,
            page: pageNumber.value,
        });
    } catch (error) {
        loadError.value = errorMessage(error, t('agents.unanswered.load_error'));
    } finally {
        isLoading.value = false;
    }
}

watch([filter, includeRevised], () => {
    pageNumber.value = 1;
    void load();
});

function goToPage(offset: number) {
    pageNumber.value += offset;
    void load();
}

onMounted(load);

// The row being revised; the dialog shows its question and reply.
const reviseOpen = ref(false);
const revising = ref<UnansweredQuestion | null>(null);

function openRevise(row: UnansweredQuestion) {
    revising.value = row;
    reviseOpen.value = true;
}

function onRevised(messageId: string) {
    const row = rows.value.find((candidate) => candidate.message_id === messageId);
    if (row) row.revised = true;
}
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.unanswered.title')" :description="t('agents.unanswered.description')">
        <template #header-icon>
            <CircleHelp class="h-5 w-5" />
        </template>

        <div class="space-y-4">
            <p
                v-if="!chatlogEnabled"
                class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                {{ t('agents.unanswered.chatlog_off') }}
            </p>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <Tabs v-model="filter">
                    <TabsList>
                        <TabsTrigger value="all">{{ t('agents.unanswered.filter_all') }}</TabsTrigger>
                        <TabsTrigger value="not_found">{{ t('agents.unanswered.filter_not_found') }}</TabsTrigger>
                        <TabsTrigger value="thumbs_down">{{ t('agents.unanswered.filter_thumbs_down') }}</TabsTrigger>
                    </TabsList>
                </Tabs>
                <div class="flex items-center gap-2">
                    <Switch id="include-revised" v-model="includeRevised" />
                    <Label for="include-revised" class="text-sm">{{ t('agents.unanswered.include_revised') }}</Label>
                </div>
            </div>

            <div v-if="isLoading && !result" class="flex justify-center py-16">
                <LoaderCircle class="text-muted-foreground h-6 w-6 animate-spin" />
            </div>

            <div v-else-if="loadError" class="flex flex-col items-center gap-2 py-16 text-sm">
                <p class="text-destructive">{{ loadError }}</p>
                <Button variant="outline" size="sm" @click="load">{{ t('agents.unanswered.try_again') }}</Button>
            </div>

            <Card v-else-if="rows.length === 0">
                <CardContent class="text-muted-foreground py-12 text-center text-sm">{{ t('agents.unanswered.empty') }}</CardContent>
            </Card>

            <ul v-else class="space-y-3" :class="{ 'opacity-60': isLoading }">
                <li v-for="row in rows" :key="row.message_id">
                    <Card>
                        <CardContent class="space-y-3 pt-5">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <Badge v-if="row.not_found" variant="secondary">{{ t('agents.unanswered.reason_not_found') }}</Badge>
                                <Badge v-if="row.thumbs_down" variant="destructive">{{ t('agents.unanswered.reason_thumbs_down') }}</Badge>
                                <Badge v-if="row.revised" variant="outline" class="text-green-700 dark:text-green-400">
                                    {{ t('agents.unanswered.revised') }}
                                </Badge>
                                <span class="text-muted-foreground ms-auto">{{ formatDateTime(row.created_at) }}</span>
                            </div>

                            <div>
                                <p class="text-muted-foreground text-xs font-medium">{{ t('agents.unanswered.question') }}</p>
                                <p dir="auto" class="mt-0.5 text-sm font-medium break-words whitespace-pre-wrap">
                                    {{ row.question || t('agents.unanswered.no_question') }}
                                </p>
                            </div>

                            <div>
                                <p class="text-muted-foreground text-xs font-medium">{{ t('agents.unanswered.answer') }}</p>
                                <p dir="auto" class="text-muted-foreground mt-0.5 line-clamp-3 text-sm break-words whitespace-pre-wrap">
                                    {{ row.answer }}
                                </p>
                            </div>

                            <p
                                v-if="row.feedback_comment"
                                dir="auto"
                                class="border-s-2 border-red-300 ps-2 text-xs break-words whitespace-pre-wrap text-red-700 dark:border-red-800 dark:text-red-300"
                            >
                                <span class="font-medium">{{ t('agents.chatlog.messages.feedback_comment') }}</span>
                                {{ row.feedback_comment }}
                            </p>

                            <div class="flex flex-wrap items-center gap-2">
                                <Button v-if="canRevise && !row.revised" size="sm" class="h-8" @click="openRevise(row)">
                                    <RotateCcw class="me-1 h-3.5 w-3.5" />
                                    {{ t('agents.unanswered.add_answer') }}
                                </Button>
                                <Button variant="outline" size="sm" class="h-8" as-child>
                                    <Link :href="chatLogUrl(agent.external_id, row.conversation_id)">
                                        <ExternalLink class="me-1 h-3.5 w-3.5" />
                                        {{ t('agents.unanswered.open_conversation') }}
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </li>
            </ul>

            <div v-if="result && result.last_page > 1" class="flex items-center justify-between">
                <Button variant="ghost" size="sm" :disabled="pageNumber <= 1 || isLoading" @click="goToPage(-1)">
                    {{ t('agents.chatlog.pagination.prev') }}
                </Button>
                <span class="text-muted-foreground text-xs tabular-nums">{{ result.current_page }} / {{ result.last_page }}</span>
                <Button variant="ghost" size="sm" :disabled="pageNumber >= result.last_page || isLoading" @click="goToPage(1)">
                    {{ t('agents.chatlog.pagination.next') }}
                </Button>
            </div>
        </div>

        <ReviseAnswerDialog
            v-model:open="reviseOpen"
            :agent-id="agent.external_id"
            :message-id="revising?.message_id ?? null"
            :user-message="revising?.question ?? ''"
            :bot-response="revising?.answer ?? ''"
            @saved="onRevised"
        />
    </AgentLayout>
</template>
