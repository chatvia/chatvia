<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import type { Auth, SharedData } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Check, ChevronsUpDown, Loader2, Plus, Settings, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Team {
    id: number;
    external_id: string;
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
        const response = await axios.get(route('my.teams.list'));
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

const isSwitching = ref(false);

const switchTeam = (team: Team) => {
    if (team.id === currentTeam.value?.id || isSwitching.value) return;

    isSwitching.value = true;
    isOpen.value = false;

    router.post(
        // Route binding is by external_id (see Team::getRouteKeyName) — keep
        // the in-memory id around for the currentTeam comparison above but
        // hand Stripe-friendly UUIDs to Ziggy.
        route('teams.switch', team.external_id),
        {},
        {
            preserveState: false,
            onFinish: () => {
                isSwitching.value = false;
            },
        },
    );
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
                <Button variant="outline" class="h-9 gap-2 px-3">
                    <Avatar class="size-5">
                        <AvatarFallback class="bg-primary/10 text-primary text-[10px] font-medium">
                            {{ currentTeam ? getInitials(currentTeam.name) : '?' }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="max-w-[120px] truncate text-sm font-medium">
                        {{ currentTeam?.name || t('team_switcher.select_team') }}
                    </span>
                    <ChevronsUpDown class="size-4 opacity-50" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" class="w-56">
                <DropdownMenuLabel class="text-muted-foreground text-xs">{{ t('teams.index.title') }}</DropdownMenuLabel>
                <div class="max-h-[300px] overflow-y-auto">
                    <div v-if="isLoading" class="flex justify-center px-2 py-4">
                        <Loader2 class="text-muted-foreground size-4 animate-spin" />
                    </div>
                    <template v-else>
                        <DropdownMenuItem v-for="team in teams" :key="team.id" class="cursor-pointer gap-2" @select.prevent="switchTeam(team)">
                            <Avatar class="size-6">
                                <AvatarFallback class="bg-primary/10 text-primary text-[10px] font-medium">
                                    {{ getInitials(team.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="flex-1 truncate">{{ team.name }}</span>
                            <Check v-if="team.id === currentTeam?.id" class="text-primary size-4" />
                        </DropdownMenuItem>
                        <div v-if="teams.length === 0" class="text-muted-foreground px-2 py-4 text-center text-sm">
                            {{ t('team_switcher.no_teams') }}
                        </div>
                    </template>
                </div>
                <template v-if="!isStaff">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="route('teams.index')" class="cursor-pointer gap-2">
                            <Settings class="size-4" />
                            {{ t('team_switcher.manage_teams') }}
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem class="cursor-pointer gap-2" @click="openCreateDialog">
                        <Plus class="size-4" />
                        {{ t('teams.index.create_team') }}
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>

        <!-- Create Team Dialog -->
        <Dialog v-model:open="isCreateDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('teams.index.create_dialog.title') }}</DialogTitle>
                    <DialogDescription>{{ t('teams.index.create_dialog.description') }}</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="createTeam">
                    <div class="grid gap-4 py-4">
                        <div class="grid gap-2">
                            <Label for="team-name">{{ t('teams.index.create_dialog.name_label') }}</Label>
                            <div class="relative">
                                <Users class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                <Input
                                    id="team-name"
                                    v-model="createForm.name"
                                    :placeholder="t('teams.index.create_dialog.name_placeholder')"
                                    class="ps-9"
                                    required
                                />
                            </div>
                            <InputError :message="createForm.errors.name" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isCreateDialogOpen = false">{{ t('teams.common.cancel') }}</Button>
                        <Button type="submit" :disabled="createForm.processing">
                            <Loader2 v-if="createForm.processing" class="me-2 size-4 animate-spin" />
                            <Plus v-else class="me-2 size-4" />
                            {{ t('teams.index.create_team') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
