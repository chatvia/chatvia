<script setup lang="ts">
// "Team Members" table. Managers get a per-row menu (change role, remove from
// the team, delete from the workspace) for everyone except the owner and
// themselves; the page owns the dialogs those actions open.
import { Edit, MoreHorizontal, Trash2, Users, UserX } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';
import { roleBadgeVariant } from '../composables/roleBadgeVariant';

type TeamMemberData = Domain.Membership.Team.Data.Resources.Members.TeamMemberData;

defineProps<{
    members: TeamMemberData[];
    currentUserId: number;
    canManageMembers: boolean;
}>();

const emit = defineEmits<{
    changeRole: [member: TeamMemberData];
    remove: [member: TeamMemberData];
    deleteFromWorkspace: [member: TeamMemberData];
}>();

const { t } = useI18n();
</script>

<template>
    <!-- Team Members -->
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Users class="size-5" />
                {{ t('teams.show.members.title') }}
            </CardTitle>
            <CardDescription>{{ t('teams.show.members.description') }}</CardDescription>
        </CardHeader>
        <CardContent>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{{ t('teams.show.members.table.member') }}</TableHead>
                        <TableHead>{{ t('teams.show.role') }}</TableHead>
                        <TableHead class="text-end">{{ t('teams.show.actions') }}</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="member in members" :key="member.id">
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <Avatar class="size-9">
                                    <AvatarFallback class="bg-muted text-xs">
                                        {{ getInitials(member.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-medium">{{ member.name }}</span>
                                        <Badge v-if="member.id === currentUserId" variant="outline" class="text-xs">{{
                                            t('teams.show.members.you')
                                        }}</Badge>
                                    </div>
                                    <span class="text-muted-foreground text-sm">{{ member.email }}</span>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <Badge :variant="roleBadgeVariant(member.role)">
                                {{ member.role_name }}
                            </Badge>
                        </TableCell>
                        <TableCell class="text-end">
                            <DropdownMenu v-if="canManageMembers && !member.is_owner && member.id !== currentUserId">
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8" :aria-label="t('teams.index.open_menu')">
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="emit('changeRole', member)">
                                        <Edit class="me-2 size-4" />
                                        {{ t('teams.show.members.change_role') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" @click="emit('remove', member)">
                                        <Trash2 class="me-2 size-4" />
                                        {{ t('teams.show.members.remove') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem variant="destructive" @click="emit('deleteFromWorkspace', member)">
                                        <UserX class="me-2 size-4" />
                                        {{ t('teams.show.members.delete_from_workspace') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </CardContent>
    </Card>
</template>
