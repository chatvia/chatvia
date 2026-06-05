<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
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
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Loader2, LogOut, MoreHorizontal, Plus, Search, Settings, Trash2, Users } from 'lucide-vue-next';

type Team = Domain.Membership.Team.Data.Resources.Team.TeamListItemData;

interface Props {
    teams: Team[];
    availableRoles: Record<string, { name: string; description: string; permissions: string[] }>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Teams',
        href: '/my/teams',
    },
];

const searchQuery = ref('');
const isCreateDialogOpen = ref(false);

const createForm = useForm({
    name: '',
});

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

const createTeam = () => {
    createForm.post(route('teams.store'), {
        onSuccess: () => {
            isCreateDialogOpen.value = false;
            createForm.reset();
        },
    });
};

const leaveTeam = () => {
    if (!teamToLeave.value) return;

    leaveForm.post(route('teams.leave', teamToLeave.value.external_id), {
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

    deleteForm.delete(route('teams.destroy', teamToDelete.value.external_id), {
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

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Teams" />

        <div class="mx-auto w-full max-w-6xl space-y-6 py-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold">Teams</h1>
                    <p class="text-muted-foreground text-sm">Manage your teams and collaborate with others.</p>
                </div>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="mr-1 h-4 w-4" />
                    Create Team
                </Button>
            </div>

            <div class="relative max-w-sm">
                <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                <Input v-model="searchQuery" placeholder="Search teams" class="pl-9" />
            </div>

            <!-- Empty state -->
            <Card v-if="!teams || teams.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Users class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No teams yet</h3>
                <p class="text-muted-foreground mt-2 text-center">Create your first team to start collaborating with others</p>
                <Button size="lg" class="mt-6 flex items-center gap-2" @click="isCreateDialogOpen = true">
                    <Plus class="h-4 w-4" />
                    Create Team
                </Button>
            </Card>

            <!-- No search results -->
            <div v-else-if="filteredTeams.length === 0" class="text-muted-foreground py-12 text-center text-sm">
                No teams found matching "{{ searchQuery }}".
                <Button variant="link" class="ml-1 h-auto p-0" @click="searchQuery = ''">Clear search</Button>
            </div>

            <!-- Teams table -->
            <div v-else class="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="h-11 pl-5">Team name</TableHead>
                            <TableHead class="h-11 w-28">Agents</TableHead>
                            <TableHead class="h-11 w-28">Members</TableHead>
                            <TableHead class="h-11 w-40">Created</TableHead>
                            <TableHead class="h-11 w-16 pr-5"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="team in filteredTeams"
                            :key="team.id"
                            class="hover:bg-muted/40 cursor-pointer"
                            @click="router.visit(route('teams.show', team.external_id))"
                        >
                            <TableCell class="py-4 pl-5">
                                <div class="flex items-center gap-4">
                                    <div class="bg-primary/10 text-primary flex h-10 w-10 shrink-0 items-center justify-center rounded-md">
                                        <Users class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="leading-tight font-medium">{{ team.name }}</p>
                                            <Badge v-if="team.is_primary" variant="secondary">Primary</Badge>
                                        </div>
                                        <p class="text-muted-foreground mt-1 line-clamp-1 text-xs">{{ formatRole(team.role) }}</p>
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell class="py-4 text-sm">{{ team.agents_count }}</TableCell>
                            <TableCell class="py-4 text-sm">{{ team.members_count }}</TableCell>
                            <TableCell class="text-muted-foreground py-4 text-sm">{{ formatDate(team.created_at) }}</TableCell>
                            <TableCell class="py-4 pr-5 text-right" @click.stop>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="h-8 w-8">
                                            <MoreHorizontal class="h-4 w-4" />
                                            <span class="sr-only">Open menu</span>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('teams.show', team.external_id)" class="cursor-pointer">
                                                <Settings class="mr-2 h-4 w-4" />
                                                Manage
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="!team.is_owner"
                                            class="text-destructive focus:text-destructive cursor-pointer"
                                            @select="teamToLeave = team"
                                        >
                                            <LogOut class="mr-2 h-4 w-4" />
                                            Leave
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="canDeleteTeam(team)"
                                            class="text-destructive focus:text-destructive cursor-pointer"
                                            @select="openDelete(team)"
                                        >
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- Leave Team Confirmation -->
        <AlertDialog :open="!!teamToLeave" @update:open="(open) => !open && (teamToLeave = null)">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Leave Team</AlertDialogTitle>
                    <AlertDialogDescription> Are you sure you want to leave "{{ teamToLeave?.name }}"? </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <Button variant="destructive" @click="leaveTeam" :disabled="leaveForm.processing">
                        <Loader2 v-if="leaveForm.processing" class="mr-2 size-4 animate-spin" />
                        Leave
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete Team Confirmation -->
        <AlertDialog :open="!!teamToDelete" @update:open="(open) => !open && (teamToDelete = null)">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Team</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{{ teamToDelete?.name }}"? This cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <div v-if="deleteForm.errors.team" class="bg-destructive/10 text-destructive rounded-md p-3 text-sm">
                    {{ deleteForm.errors.team }}
                </div>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <Button variant="destructive" @click="deleteTeam" :disabled="deleteForm.processing">
                        <Loader2 v-if="deleteForm.processing" class="mr-2 size-4 animate-spin" />
                        Delete
                    </Button>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Create Team Dialog -->
        <Dialog v-model:open="isCreateDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Create New Team</DialogTitle>
                    <DialogDescription> Create a team to collaborate with others. </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="createTeam">
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label for="team-name">Team Name</Label>
                            <Input id="team-name" v-model="createForm.name" placeholder="Enter team name" required />
                            <InputError :message="createForm.errors.name" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isCreateDialogOpen = false"> Cancel </Button>
                        <Button type="submit" :disabled="createForm.processing">
                            <Loader2 v-if="createForm.processing" class="mr-2 size-4 animate-spin" />
                            Create Team
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
