<script setup lang="ts">
// The workspace chatter table: status, agent count, message usage against the
// monthly limit and join date, with password-reset and remove buttons per row.
// Clicking a row opens the chatter's detail sheet.
import type { WorkspaceChatterRow } from '@/api/chatters';
import ChatterNameCell from '@/components/chatters/ChatterNameCell.vue';
import ChatterStatusBadge from '@/components/chatters/ChatterStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { KeyRound, LoaderCircle, Trash2, UserPlus, Users } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    /** Rows after the search filter. */
    members: WorkspaceChatterRow[];
    /** Number of rows before the search filter (tells "no chatters" from "no matches"). */
    total: number;
    isLoading: boolean;
    canInvite: boolean;
}>();

const emit = defineEmits<{
    open: [member: WorkspaceChatterRow];
    reset: [member: WorkspaceChatterRow];
    delete: [member: WorkspaceChatterRow];
    invite: [];
}>();

const { t } = useI18n();
const { formatDate } = useDateFormat();
</script>

<template>
    <Table>
        <TableHeader>
            <TableRow>
                <TableHead>{{ t('workspace.members.table.name') }}</TableHead>
                <TableHead>{{ t('workspace.members.table.email') }}</TableHead>
                <TableHead>{{ t('workspace.members.table.status') }}</TableHead>
                <TableHead>{{ t('workspace.members.table.agents') }}</TableHead>
                <TableHead>{{ t('workspace.members.table.messages') }}</TableHead>
                <TableHead>{{ t('workspace.members.table.created') }}</TableHead>
                <TableHead class="w-32 text-end">{{ t('workspace.members.table.actions') }}</TableHead>
            </TableRow>
        </TableHeader>
        <TableBody>
            <TableRow v-if="isLoading">
                <TableCell colspan="7" class="py-8 text-center">
                    <LoaderCircle class="text-muted-foreground mx-auto h-8 w-8 animate-spin" />
                </TableCell>
            </TableRow>
            <TableRow v-else-if="members.length === 0">
                <TableCell colspan="7" class="text-muted-foreground py-8 text-center">
                    <div v-if="total === 0">
                        <Users class="text-muted-foreground/50 mx-auto mb-4 h-12 w-12" />
                        <p>{{ t('workspace.members.empty.title') }}</p>
                        <p class="text-sm">{{ t('workspace.members.empty.description') }}</p>
                        <Button v-if="canInvite" size="sm" class="mt-4" @click="emit('invite')">
                            <UserPlus class="me-2 h-4 w-4" />
                            {{ t('workspace.members.empty.invite_first') }}
                        </Button>
                    </div>
                    <div v-else>
                        <p>{{ t('workspace.members.no_results.title') }}</p>
                        <p class="text-sm">{{ t('workspace.members.no_results.description') }}</p>
                    </div>
                </TableCell>
            </TableRow>
            <TableRow v-for="member in members" :key="member.external_id" class="hover:bg-muted/40 cursor-pointer" @click="emit('open', member)">
                <ChatterNameCell :name="member.name" :avatar="member.avatar" />
                <TableCell>
                    {{ member.email || t('workspace.members.no_email') }}
                </TableCell>
                <TableCell>
                    <ChatterStatusBadge
                        :status="member.status"
                        :deactivated="member.is_deactivated"
                        :pending-label="t('workspace.members.status.pending')"
                        :active-label="t('workspace.members.status.active')"
                        :deactivated-label="t('workspace.members.status.deactivated')"
                    />
                </TableCell>
                <TableCell>
                    {{ member.agents_count }}
                </TableCell>
                <TableCell>
                    <span
                        v-if="member.messages_limit !== null"
                        class="text-sm tabular-nums"
                        :class="member.messages_used >= member.messages_limit ? 'text-destructive font-medium' : ''"
                    >
                        {{ member.messages_used }} / {{ member.messages_limit }}
                    </span>
                    <span v-else class="text-muted-foreground text-sm">—</span>
                </TableCell>
                <TableCell>
                    {{ formatDate(member.created_at) }}
                </TableCell>
                <TableCell class="text-end" @click.stop>
                    <div class="flex items-center justify-end gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0"
                            :title="t('workspace.members.reset_title', { name: member.name })"
                            :aria-label="t('workspace.members.reset_title', { name: member.name })"
                            :disabled="!member.email || member.status === 'pending'"
                            @click="emit('reset', member)"
                        >
                            <KeyRound class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive hover:text-destructive h-8 w-8 p-0"
                            :title="t('workspace.members.remove_title', { name: member.name })"
                            :aria-label="t('workspace.members.remove_title', { name: member.name })"
                            @click="emit('delete', member)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </TableCell>
            </TableRow>
        </TableBody>
    </Table>
</template>
