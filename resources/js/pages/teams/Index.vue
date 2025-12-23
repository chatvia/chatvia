<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import InputError from '@/components/InputError.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Loader2, LogOut, Plus, Search, Trash2, Users } from 'lucide-vue-next';

type Team = App.Data.Resources.Team.TeamListItemData;

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

const leaveTeam = (team: Team) => {
    leaveForm.post(route('teams.leave', team.id), {});
};

const deleteTeam = () => {
    if (!teamToDelete.value) return;

    deleteForm.delete(route('teams.destroy', teamToDelete.value.id), {
        onSuccess: () => {
            teamToDelete.value = null;
        },
    });
};

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

        <div class="container mx-auto max-w-5xl py-8">
            <!-- Header -->
            <div class="mb-8 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Teams</h1>
                    <p class="text-muted-foreground mt-1">Manage your teams and collaborate with others</p>
                </div>
                <Button size="lg" @click="isCreateDialogOpen = true" class="flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    Create Team
                </Button>
            </div>

            <!-- Search -->
            <div class="mb-6 flex items-center">
                <div class="relative flex-1">
                    <Search class="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                    <Input v-model="searchQuery" class="pl-10" placeholder="Search teams..." />
                </div>
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
            <Card v-else-if="filteredTeams.length === 0" class="flex h-64 flex-col items-center justify-center">
                <Search class="text-muted-foreground h-12 w-12 opacity-50" />
                <h3 class="mt-4 text-xl font-medium">No matching teams</h3>
                <p class="text-muted-foreground mt-2 text-center">No teams found matching "{{ searchQuery }}"</p>
                <Button variant="outline" class="mt-6" @click="searchQuery = ''"> Clear Search </Button>
            </Card>

            <!-- Teams Grid -->
            <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="team in filteredTeams" :key="team.id" class="overflow-hidden">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="flex items-center gap-2 text-xl">
                                <Users class="h-5 w-5 flex-shrink-0" />
                                <span class="truncate">
                                    <Link :href="route('teams.show', team.id)">{{ team.name }}</Link>
                                </span>
                            </CardTitle>
                            <div v-if="team.is_primary" class="bg-primary/10 text-primary rounded-full px-2.5 py-1 text-xs font-medium">Primary</div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div class="flex flex-col">
                                <span class="text-muted-foreground">Agents</span>
                                <span class="text-lg font-medium">{{ team.agents_count }}</span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-muted-foreground">Members</span>
                                <span class="text-lg font-medium">{{ team.members_count }}</span>
                            </div>
                        </div>
                    </CardContent>

                    <CardFooter class="flex justify-between border-t p-4">
                        <div class="flex items-center gap-1">
                            <!-- Leave Team -->
                            <AlertDialog v-if="!team.is_owner">
                                <AlertDialogTrigger as-child>
                                    <Button variant="ghost" size="sm" class="text-muted-foreground hover:text-destructive">
                                        <LogOut class="mr-1 h-4 w-4" />
                                        Leave
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Leave Team</AlertDialogTitle>
                                        <AlertDialogDescription> Are you sure you want to leave "{{ team.name }}"? </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                                        <AlertDialogAction
                                            class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                                            @click="leaveTeam(team)"
                                            :disabled="leaveForm.processing"
                                        >
                                            Leave
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>

                            <!-- Delete Team -->
                            <Button
                                v-if="canDeleteTeam(team)"
                                variant="ghost"
                                size="sm"
                                class="text-muted-foreground hover:text-destructive"
                                @click.prevent="
                                    teamToDelete = team;
                                    deleteForm.clearErrors();
                                "
                            >
                                <Trash2 class="mr-1 h-4 w-4" />
                                Delete
                            </Button>
                        </div>

                        <Button as-child size="sm">
                            <Link :href="route('teams.show', team.id)"> Manage </Link>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </div>

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
