<script setup lang="ts">
// "Pending Invitations" table: email, role, when it was sent, and for
// managers a resend (with a per-row spinner) and a cancel button.
import { useForm } from '@inertiajs/vue3';
import { Clock, Loader2, Mail, Send, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import { roleBadgeVariant } from '../composables/roleBadgeVariant';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;
type TeamRoleData = Domain.Membership.Team.Data.Resources.Roles.TeamRoleData;
type TeamInvitationData = Domain.Membership.Team.Data.Resources.Members.TeamInvitationData;

const props = defineProps<{
    team: TeamData;
    invitations: TeamInvitationData[];
    roles: TeamRoleData[];
    canManageMembers: boolean;
}>();

const { t } = useI18n();
const { formatDate } = useDateFormat();

const roleName = (code: string | null): string | null => props.roles.find((r) => r.code === code)?.name || code;

const cancelInvitationForm = useForm({});
const cancelInvitation = (invitationId: number) => {
    cancelInvitationForm.delete(route('teams.cancel-invitation', { team: props.team.external_id, invitationId }));
};

const resendInvitationForm = useForm({});
const resendingInvitationId = ref<number | null>(null);
const resendInvitation = (invitationId: number) => {
    resendingInvitationId.value = invitationId;
    resendInvitationForm.post(route('teams.resend-invitation', { team: props.team.external_id, invitationId }), {
        preserveScroll: true,
        onSuccess: () => toast.success(t('teams.show.invitations.toast_resent')),
        onError: (errors) => toast.error(errors.invitation || t('teams.show.invitations.toast_resend_failed')),
        onFinish: () => {
            resendingInvitationId.value = null;
        },
    });
};
</script>

<template>
    <!-- Pending Invitations -->
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Mail class="size-5" />
                {{ t('teams.show.invitations.title') }}
            </CardTitle>
            <CardDescription>{{ t('teams.show.invitations.description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('teams.show.invitations.table.email') }}</TableHead>
                        <TableHead>{{ t('teams.show.role') }}</TableHead>
                        <TableHead>{{ t('teams.show.invitations.table.sent') }}</TableHead>
                        <TableHead class="text-end">{{ t('teams.show.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="invitation in invitations" :key="invitation.id">
                        <TableCell>
                            <div class="flex items-center gap-2">
                                <Clock class="text-muted-foreground size-4" />
                                {{ invitation.email }}
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="roleBadgeVariant(invitation.role)">
                                {{ roleName(invitation.role) }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-muted-foreground text-sm">
                            {{ formatDate(invitation.created_at) }}
                        </TableCell>
                        <TableCell class="text-end">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="canManageMembers"
                                    variant="ghost"
                                    size="sm"
                                    @click="resendInvitation(invitation.id)"
                                    :disabled="resendInvitationForm.processing"
                                >
                                    <Loader2 v-if="resendingInvitationId === invitation.id" class="me-1 size-4 animate-spin" />
                                    <Send v-else class="me-1 size-4" />
                                    {{ t('teams.show.invitations.resend') }}
                                </Button>
                                <Button
                                    v-if="canManageMembers"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="cancelInvitation(invitation.id)"
                                    :disabled="cancelInvitationForm.processing"
                                >
                                    <X class="me-1 size-4" />
                                    {{ t('teams.common.cancel') }}
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
