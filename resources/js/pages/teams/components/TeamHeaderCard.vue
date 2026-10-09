<script setup lang="ts">
// Team page header: avatar, the team name (owners can rename it inline) and
// the team's public ID, which API callers reference — click it to copy.
import { useForm } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, PencilLine, Save, X } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { getInitials } from '@/composables/useInitials';

type TeamData = Domain.Membership.Team.Data.Resources.Team.TeamData;

const props = defineProps<{
    team: TeamData;
    isOwner: boolean;
}>();

const { t } = useI18n();
const { copy } = useClipboard({ legacy: true });

// Two-second "Copied!" flash after clicking the team-id copy button.
const copiedExternalId = ref(false);
const copyExternalId = async () => {
    await copy(props.team.external_id);
    copiedExternalId.value = true;
    setTimeout(() => (copiedExternalId.value = false), 2000);
};

const isEditingName = ref(false);
// The template attaches this ref to a wrapped `Input` component; `$el` is the
// underlying <input> when present, otherwise the ref already is the input.
const teamNameInput = ref<HTMLInputElement | { $el?: HTMLInputElement } | null>(null);
const updateTeamForm = useForm({
    name: props.team.name,
});

const startEditingName = () => {
    isEditingName.value = true;
    nextTick(() => {
        const refValue = teamNameInput.value;
        const input = (refValue && '$el' in refValue ? refValue.$el : refValue) as HTMLInputElement | undefined;
        input?.focus();
        input?.select();
    });
};

const cancelEditingName = () => {
    isEditingName.value = false;
    updateTeamForm.reset();
};

const updateTeamName = () => {
    updateTeamForm.put(route('teams.update', props.team.external_id), {
        onSuccess: () => {
            isEditingName.value = false;
        },
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Avatar class="border-background size-16 border-2 shadow">
                        <AvatarFallback class="bg-primary/10 text-primary text-xl font-semibold">
                            {{ getInitials(team.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <div v-if="!isEditingName" class="flex items-center gap-2">
                            <CardTitle class="text-2xl">{{ team.name }}</CardTitle>
                            <Button
                                v-if="isOwner"
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :aria-label="t('teams.show.rename_team')"
                                @click="startEditingName"
                            >
                                <PencilLine class="size-4" />
                            </Button>
                        </div>
                        <form v-else @submit.prevent="updateTeamName" class="flex items-center gap-2">
                            <Input ref="teamNameInput" v-model="updateTeamForm.name" class="h-8 w-64" />
                            <Button
                                type="submit"
                                size="icon"
                                class="size-8"
                                :disabled="updateTeamForm.processing"
                                :aria-label="t('teams.show.save_team_name')"
                            >
                                <Save class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="size-8"
                                :aria-label="t('teams.common.cancel')"
                                @click="cancelEditingName"
                            >
                                <X class="size-4" />
                            </Button>
                        </form>

                        <!-- Team identifier — the opaque UUID API callers reference. Click
                             the chip OR the copy icon to put it on the clipboard. -->
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:bg-muted/60 hover:text-foreground mt-2 inline-flex max-w-full items-center gap-1.5 rounded-md px-2 py-1 font-mono text-xs transition-colors"
                                        @click="copyExternalId"
                                    >
                                        <span class="truncate" dir="ltr">{{ team.external_id }}</span>
                                        <component
                                            :is="copiedExternalId ? Check : Copy"
                                            class="size-3.5 shrink-0"
                                            :class="copiedExternalId ? 'text-green-500' : ''"
                                        />
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent side="bottom">
                                    {{ copiedExternalId ? t('teams.show.copied') : t('teams.show.copy_team_id') }}
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </CardHeader>
    </Card>
</template>
