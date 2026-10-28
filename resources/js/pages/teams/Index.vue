<script setup lang="ts">
// Teams list: search, open a team, leave a team you were invited to, delete an
// owned team (never the primary one or your last owned team), create a team.
// Without a license key the workspace keeps only its first team: the create
// buttons give way to a notice with a link to buy a key.
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import CreateTeamDialog from '@/components/CreateTeamDialog.vue';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useDateFormat } from '@/composables/useDateFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import { destroy as destroyTeam, leave as leaveTeamRoute, show as showTeam } from '@/routes/teams';
import { type BreadcrumbItem } from '@/types';
import { Loader2, LogOut, MoreHorizontal, Plus, Search, Settings, Trash2, Users } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { formatDate } = useDateFormat();

type Team = Domain.Membership.Team.Data.Resources.Team.TeamListItemData;

interface Props {
    teams: Team[];
    availableRoles: Record<string, { name: string; description: string; permissions: string[] }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('teams.breadcrumb'),
        href: '/my/teams',
    },
];

const searchQuery = ref('');
const isCreateDialogOpen = ref(false);

const leaveForm = useForm({});
const deleteForm = useForm<{ team?: string }>({});
const teamToLeave = ref<Team | null>(null);
const teamToDelete = ref<Team | null>(null);

const filteredTeams = computed(() => {
    if (!searchQuery.value) return props.teams || [];

    const query = searchQuery.value.toLowerCase();
    return (props.teams || []).filter((team) => team.name.toLowerCase().includes(query) || team.role.toLowerCase().includes(query));
});

const ownedTeamsCount = computed(() => (props.teams || []).filter((t) => t.is_owner).length);
const canDeleteTeam = (team: Team) => team.is_owner && !team.is_primary && ownedTeamsCount.value > 1;

const leaveTeam = () => {
    if (!teamToLeave.value) return;

    leaveForm.post(leaveTeamRoute.url(teamToLeave.value.external_id), {
        onSuccess: () => {
            teamToLeave.value = null;
        },
    });
};

const openDelete = (team: Team) => {
    teamToDelete.value = team;
    deleteForm.clearErrors();
};

const deleteTeam = () => {
    if (!teamToDelete.value) return;

    deleteForm.delete(destroyTeam.url(teamToDelete.value.external_id), {
        onSuccess: () => {
            teamToDelete.value = null;
        },
    });
};

const formatRole = (role: string) =>
    role
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('teams.index.title')" />

        <div class="mx-auto w-full max-w-6xl space-y-6 py-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">{{ t('teams.index.heading') }}</h1>
                    <p class="text-muted-foreground text-sm">{{ t('teams.index.subtitle') }}</p>
                </div>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="me-1 h-4 w-4" />
                    {{ t('teams.index.create_team') }}
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
                <Input v-model="searchQuery" :placeholder="t('teams.index.search_placeholder')" class="ps-9" />
            </div>

            <!-- Empty state -->
            <Card v-if="!teams || teams.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Users class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">{{ t('teams.index.empty_title') }}</h3>
                <p class="text-muted-foreground mt-2 text-center">{{ t('teams.index.empty_description') }}</p>
                <Button size="lg" class="mt-6 flex items-center gap-2" @click="isCreateDialogOpen = true">
                    <Plus class="h-4 w-4" />
                    {{ t('teams.index.create_team') }}
                </Button>
            </Card>

            <!-- No search results -->
            <div v-else-if="filteredTeams.length === 0" class="text-muted-foreground py-12 text-center text-sm">
                {{ t('teams.index.no_results', { query: searchQuery }) }}
                <Button variant="link" class="ms-1 h-auto p-0" @click="searchQuery = ''">{{ t('teams.index.clear_search') }}</Button>
            </div>

            <!-- Teams table -->
            <Table v-else>
                <TableHeader>
                    <TableRow>
                        <TableHead class="h-11 ps-5">{{ t('teams.index.table.name') }}</TableHead>
                        <TableHead class="h-11 w-28">{{ t('teams.index.table.agents') }}</TableHead>
                        <TableHead class="h-11 w-28">{{ t('teams.index.table.members') }}</TableHead>
                        <TableHead class="h-11 w-40">{{ t('teams.index.table.created') }}</TableHead>
                        <TableHead class="h-11 w-16 pe-5"></TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="team in filteredTeams"
                        :key="team.id"
                        class="hover:bg-muted/40 cursor-pointer"
                        @click="router.visit(showTeam.url(team.external_id))"
                    >
                        <TableCell class="py-4 ps-5">
                            <div class="flex items-center gap-4">
                                <div class="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                    <Users class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="leading-tight font-medium">{{ team.name }}</p>
                                        <Badge v-if="team.is_primary" variant="secondary">{{ t('teams.index.primary') }}</Badge>
                                    </div>
                                    <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ formatRole(team.role) }}</p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="py-4 text-sm">{{ team.agents_count }}</TableCell>
                        <TableCell class="py-4 text-sm">{{ team.members_count }}</TableCell>
                        <TableCell class="text-muted-foreground py-4 text-sm">{{ formatDate(team.created_at) }}</TableCell>
                        <TableCell class="py-4 pe-5 text-end" @click.stop>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                        <MoreHorizontal class="h-4 w-4" />
                                        <span class="sr-only">{{ t('teams.index.open_menu') }}</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem as-child>
                                        <Link :href="showTeam.url(team.external_id)" class="cursor-pointer">
                                            <Settings class="me-2 h-4 w-4" />
                                            {{ t('teams.index.manage') }}
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem v-if="!team.is_owner" variant="destructive" class="cursor-pointer" @select="teamToLeave = team">
                                        <LogOut class="me-2 h-4 w-4" />
                                        {{ t('teams.index.leave') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="canDeleteTeam(team)"
                                        variant="destructive"
                                        class="cursor-pointer"
                                        @select="openDelete(team)"
                                    >
                                        <Trash2 class="me-2 h-4 w-4" />
                                        {{ t('teams.index.delete') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <!-- Leave Team Confirmation -->
        <AlertDialog :open="!!teamToLeave" @update:open="(open) => !open && (teamToLeave = null)">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('teams.index.leave_dialog.title') }}</AlertDialogTitle>
                    <AlertDialogDescription>{{ t('teams.index.leave_dialog.description', { name: teamToLeave?.name }) }}</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('teams.common.cancel') }}</AlertDialogCancel>
                    <Button variant="destructive" @click="leaveTeam" :disabled="leaveForm.processing">
                        <Loader2 v-if="leaveForm.processing" class="me-2 size-4 animate-spin" />
                        {{ t('teams.index.leave') }}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete Team Confirmation -->
        <AlertDialog :open="!!teamToDelete" @update:open="(open) => !open && (teamToDelete = null)">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{{ t('teams.index.delete_dialog.title') }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {{ t('teams.index.delete_dialog.description', { name: teamToDelete?.name }) }}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div v-if="deleteForm.errors.team" class="bg-destructive/10 text-destructive rounded-md p-3 text-sm">
                    {{ deleteForm.errors.team }}
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel>{{ t('teams.common.cancel') }}</AlertDialogCancel>
                    <Button variant="destructive" @click="deleteTeam" :disabled="deleteForm.processing">
                        <Loader2 v-if="deleteForm.processing" class="me-2 size-4 animate-spin" />
                        {{ t('teams.index.delete') }}
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <CreateTeamDialog v-model:open="isCreateDialogOpen" />
    </AppLayout>
</template>
