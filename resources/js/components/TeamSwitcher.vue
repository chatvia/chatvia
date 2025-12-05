<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
import type { SharedData } from '@/types';

interface Team {
    id: number;
    name: string;
    is_owner: boolean;
    is_primary: boolean;
}

const page = usePage<SharedData>();
const currentTeam = computed(() => page.props.currentTeam as Team | null);
const allTeams = computed(() => (page.props.allTeams as Team[]) || []);

const isOpen = ref(false);
const isCreateDialogOpen = ref(false);

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
        },
    });
};
</script>

<template>
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
                <DropdownMenuItem
                    v-for="team in allTeams"
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
                <div v-if="allTeams.length === 0" class="px-2 py-4 text-sm text-muted-foreground text-center">
                    No teams available
                </div>
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <a :href="route('teams.index')" class="gap-2 cursor-pointer">
                    <Settings class="size-4" />
                    Manage Teams
                </a>
            </DropdownMenuItem>
            <DropdownMenuItem class="gap-2 cursor-pointer" @click="openCreateDialog">
                <Plus class="size-4" />
                Create Team
            </DropdownMenuItem>
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
</template>

