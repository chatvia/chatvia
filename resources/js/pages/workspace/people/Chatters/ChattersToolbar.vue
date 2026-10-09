<script setup lang="ts">
// Search box, "x of y chatters" count and the Invite button. Without a
// protected agent nobody can be invited, so the button is disabled and a
// tooltip explains why.
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Search, UserPlus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    shown: number;
    total: number;
    canInvite: boolean;
}>();

const emit = defineEmits<{
    invite: [];
}>();

const search = defineModel<string>('search', { required: true });

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="relative w-full sm:w-72">
            <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2" />
            <Input v-model="search" :placeholder="t('workspace.members.search_placeholder')" class="ps-10" />
        </div>

        <div class="flex w-full items-center justify-between gap-4 sm:w-auto">
            <div v-if="total > 0" class="text-muted-foreground text-sm">
                {{ t('workspace.members.count', { shown, total }, total) }}
            </div>
            <TooltipProvider v-if="!canInvite" :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <!-- A disabled button suppresses pointer events, so the tooltip
                             hangs off a focusable span wrapper instead. -->
                        <span tabindex="0" class="inline-flex">
                            <Button size="sm" disabled>
                                <UserPlus class="me-2 h-4 w-4" />
                                {{ t('workspace.members.invite') }}
                            </Button>
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        <p>{{ t('workspace.members.invite_disabled_hint') }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <Button v-else size="sm" @click="emit('invite')">
                <UserPlus class="me-2 h-4 w-4" />
                {{ t('workspace.members.invite') }}
            </Button>
        </div>
    </div>
</template>
