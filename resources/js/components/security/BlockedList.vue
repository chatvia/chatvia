<script setup lang="ts">
// Who cannot chat with an agent, loaded when the list opens (Guardrails
// "Blocked" tab, Security → Blocked). A visitor block covers every agent of
// the workspace, so the visitors are the workspace's blocks, with Unblock for
// people who can manage security. Below them, the chatters who have access to
// this agent but whose account is switched off; they are turned back on from
// People.
import { listAgentBlocks, type BlockedChatter, type BlockedVisitor } from '@/api/blocking';
import BlockedVisitorsCard from '@/components/security/BlockedVisitorsCard.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useDateFormat } from '@/composables/useDateFormat';
import { useTeamPermissions } from '@/composables/useTeamPermissions';
import { index as peopleChatters } from '@/routes/people/chatters';
import { Link } from '@inertiajs/vue3';
import { LoaderCircle, UserX } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agentId: string;
}>();

const { t } = useI18n();
const { formatDateTime } = useDateFormat();
const { hasPermission } = useTeamPermissions();

const canManageSecurity = computed(() => hasPermission('account.security'));
const canManageChatters = computed(() => hasPermission('account.chatters.manage'));

const loading = ref(true);
const failed = ref(false);
const visitors = ref<BlockedVisitor[]>([]);
const chatters = ref<BlockedChatter[]>([]);

const load = async () => {
    loading.value = true;
    failed.value = false;
    try {
        const blocks = await listAgentBlocks(props.agentId);
        visitors.value = blocks.visitors;
        chatters.value = blocks.chatters;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
};

const dropVisitor = (blockId: string) => {
    visitors.value = visitors.value.filter((block) => block.external_id !== blockId);
};

onMounted(load);
</script>

<template>
    <div v-if="loading" class="text-muted-foreground flex items-center justify-center gap-2 py-12 text-sm">
        <LoaderCircle class="h-4 w-4 animate-spin" />
        {{ t('agents.guardrails.blocked_list.loading') }}
    </div>

    <div v-else-if="failed" class="flex flex-col items-center gap-3 py-12 text-sm">
        <p class="text-muted-foreground">{{ t('agents.guardrails.blocked_list.load_error') }}</p>
        <Button variant="outline" size="sm" @click="load">{{ t('errors.try_again') }}</Button>
    </div>

    <div v-else class="space-y-6">
        <BlockedVisitorsCard :blocks="visitors" :can-manage="canManageSecurity" @unblocked="dropVisitor" />

        <Card class="overflow-hidden py-0">
            <CardContent class="p-0">
                <div class="flex flex-wrap items-start justify-between gap-3 p-5">
                    <div class="flex items-start gap-3">
                        <div class="bg-background text-muted-foreground flex size-10 shrink-0 items-center justify-center rounded-lg border">
                            <UserX class="size-5" />
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-sm font-semibold">{{ t('agents.guardrails.blocked_list.chatters_title') }}</p>
                            <p class="text-muted-foreground text-sm">{{ t('agents.guardrails.blocked_list.chatters_help') }}</p>
                        </div>
                    </div>
                    <Button v-if="canManageChatters && chatters.length" as-child variant="outline" size="sm">
                        <Link :href="peopleChatters.url()">{{ t('agents.guardrails.blocked_list.manage_in_people') }}</Link>
                    </Button>
                </div>

                <ul v-if="chatters.length" class="divide-y border-t">
                    <li v-for="chatter in chatters" :key="chatter.external_id" class="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
                        <div class="min-w-0 space-y-0.5 text-sm">
                            <p class="font-medium">{{ chatter.name }}</p>
                            <p v-if="chatter.reason" class="text-muted-foreground break-words">{{ chatter.reason }}</p>
                        </div>
                        <span class="text-muted-foreground text-xs whitespace-nowrap">{{
                            t('agents.guardrails.blocked_list.since', { date: formatDateTime(chatter.deactivated_at) })
                        }}</span>
                    </li>
                </ul>
                <p v-else class="text-muted-foreground border-t px-5 py-4 text-sm">{{ t('agents.guardrails.blocked_list.no_chatters') }}</p>
            </CardContent>
        </Card>
    </div>
</template>
