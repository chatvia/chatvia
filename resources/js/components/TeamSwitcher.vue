<script setup lang="ts">
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { getInitials } from '@/composables/useInitials';
import { Check, ChevronsUpDown, Plus, Settings, Loader2 } from 'lucide-vue-next';
import type { SharedData, Auth } from '@/types';

interface Team {
    id: number;
    name: string;
    is_owner: boolean;
    is_primary: boolean;
}

const page = usePage<SharedData>();
const auth = computed(() => page.props.auth as Auth);
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const isStaff = computed(() => auth.value.user.type === 'staff');

const isOpen = ref(false);
const isCreateDialogOpen = ref(false);
const teams = ref<Team[]>([]);
const isLoading = ref(false);

const fetchTeams = async () => {
    if (teams.value.length > 0) return;
    isLoading.value = true;
    try {
        const response = await axios.get('/my/teams');
        teams.value = response.data.teams;
    } finally {
        isLoading.value = false;
    }
};

watch(isOpen, (open) => {
    if (open) fetchTeams();
});

const createForm = useForm({
    name: '',
});

const switchTeam = (teamId: number) => {
    router.post(route('teams.switch', teamId), {}, {
        preserveState: false,
    });
    isOpen.value = false;
};

const openCreateDialog = () => {
    isOpen.value = false;
    isCreateDialogOpen.value = true;
};

const createTeam = () => {
    createForm.post(route('teams.store'), {
        onSuccess: () => {
            isCreateDialogOpen.value = false;
            createForm.reset();
            teams.value = []; // Reset to refetch on next open
        },
    });
};
</script>

<template>
    <div>
        <DropdownMenu v-model:open="isOpen">
        <DropdownMenuTrigger as-child>
            <Button variant="outline" class="gap-2 h-9 px-3">
                <Avatar class="size-5">
                    <AvatarFallback class="text-[10px] bg-primary/10 text-primary font-medium">
                        {{ currentTeam ? getInitials(currentTeam.name) : '?' }}
                    </AvatarFallback>
                </Avatar>
                <span class="max-w-[120px] truncate text-sm font-medium">
                    {{ currentTeam?.name || 'Select Team' }}
                </span>
                <ChevronsUpDown class="size-4 opacity-50" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-56">
            <DropdownMenuLabel class="text-xs text-muted-foreground">
                Teams
            </DropdownMenuLabel>
            <div class="max-h-[300px] overflow-y-auto">
                <div v-if="isLoading" class="px-2 py-4 flex justify-center">
                    <Loader2 class="size-4 animate-spin text-muted-foreground" />
                </div>
                <template v-else>
                    <DropdownMenuItem
                        v-for="team in teams"
                        :key="team.id"
                        class="gap-2 cursor-pointer"
                        @click="team.id !== currentTeam?.id && switchTeam(team.id)"
                    >
                        <Avatar class="size-6">
                            <AvatarFallback class="text-[10px] bg-primary/10 text-primary font-medium">
                                {{ getInitials(team.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="flex-1 truncate">{{ team.name }}</span>
                        <Check v-if="team.id === currentTeam?.id" class="size-4 text-primary" />
                    </DropdownMenuItem>
                    <div v-if="teams.length === 0" class="px-2 py-4 text-sm text-muted-foreground text-center">
                        No teams available
                    </div>
                </template>
            </div>
            <template v-if="!isStaff">
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link :href="route('teams.index')" class="gap-2 cursor-pointer">
                        <Settings class="size-4" />
                        Manage Teams
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem class="gap-2 cursor-pointer" @click="openCreateDialog">
                    <Plus class="size-4" />
                    Create Team
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>

    <!-- Create Team Dialog -->
    <Dialog v-model:open="isCreateDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Create New Team</DialogTitle>
                <DialogDescription>
                    Create a team to collaborate with others.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="createTeam">
                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="team-name">Team Name</Label>
                        <Input
                            id="team-name"
                            v-model="createForm.name"
                            placeholder="Enter team name"
                            required
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="isCreateDialogOpen = false">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="createForm.processing">
                        <Loader2 v-if="createForm.processing" class="mr-2 size-4 animate-spin" />
                        Create Team
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
    </div>
</template>

