<script setup lang="ts">
// The agent's chatter table. Rows with access come first; a row without access
// shows a Grant button, a row with access a Revoke (trash) button. Clicking a
// row opens the chatter sheet.
import type { AgentChatterRow } from '@/api/chatters';
import ChatterNameCell from '@/components/chatters/ChatterNameCell.vue';
import ChatterStatusBadge from '@/components/chatters/ChatterStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { LoaderCircle, PlusCircle, Trash2, Users } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import ChatterAccessBadge from './ChatterAccessBadge.vue';

defineProps<{
    /** Rows after the search filter. */
    members: AgentChatterRow[];
    /** Number of rows before the search filter (tells "no chatters" from "no matches"). */
    total: number;
    isLoading: boolean;
    grantingId: string | null;
}>();

const emit = defineEmits<{
    open: [member: AgentChatterRow];
    grant: [member: AgentChatterRow];
    revoke: [member: AgentChatterRow];
}>();

const { t } = useI18n();
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>{{ t('agents.chatters.table.name') }}</TableHead>
                <TableHead>{{ t('agents.chatters.table.email') }}</TableHead>
                <TableHead>{{ t('agents.chatters.table.status') }}</TableHead>
                <TableHead>{{ t('agents.chatters.table.access') }}</TableHead>
                <TableHead class="w-32 text-end">{{ t('agents.chatters.table.actions') }}</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-if="isLoading">
                <TableCell colspan="5" class="py-8 text-center">
                    <LoaderCircle class="text-muted-foreground mx-auto h-8 w-8 animate-spin" />
                </TableCell>
            </TableRow>
            <TableRow v-else-if="members.length === 0">
                <TableCell colspan="5" class="text-muted-foreground py-8 text-center">
                    <div v-if="total === 0">
                        <Users class="text-muted-foreground/50 mx-auto mb-4 h-12 w-12" />
                        <p>{{ t('agents.chatters.empty_title') }}</p>
                        <p class="text-sm">{{ t('agents.chatters.empty_description') }}</p>
                    </div>
                    <div v-else>
                        <p>{{ t('agents.chatters.no_results_title') }}</p>
                        <p class="text-sm">{{ t('agents.chatters.no_results_description') }}</p>
                    </div>
                </TableCell>
            </TableRow>
            <TableRow
                v-for="member in members"
                :key="member.external_id"
                class="hover:bg-muted/50 cursor-pointer transition-colors"
                :class="{ 'opacity-70': !member.has_access }"
                @click="emit('open', member)"
            >
                <ChatterNameCell :name="member.name" :avatar="member.avatar" />
                <TableCell>
                    {{ member.email || t('agents.chatters.no_email') }}
                </TableCell>
                <TableCell>
                    <ChatterStatusBadge
                        :status="member.status"
                        :pending-label="t('agents.chatters.status.pending')"
                        :active-label="t('agents.chatters.status.active')"
                    />
                </TableCell>
                <TableCell>
                    <ChatterAccessBadge :has-access="member.has_access" />
                </TableCell>
                <TableCell class="text-end">
                    <Button
                        v-if="!member.has_access"
                        variant="outline"
                        size="sm"
                        class="h-8"
                        :disabled="grantingId === member.external_id"
                        :title="t('agents.chatters.grant_title', { name: member.name })"
                        @click.stop="emit('grant', member)"
                    >
                        <LoaderCircle v-if="grantingId === member.external_id" class="me-1 h-3.5 w-3.5 animate-spin" />
                        <PlusCircle v-else class="me-1 h-3.5 w-3.5" />
                        {{ t('agents.chatters.grant') }}
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive h-8 w-8 p-0"
                        :title="t('agents.chatters.revoke_title', { name: member.name })"
                        :aria-label="t('agents.chatters.revoke_title', { name: member.name })"
                        @click.stop="emit('revoke', member)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
