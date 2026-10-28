<script setup lang="ts">
// Search box, "x of y chatters" count and the Invite button above the table.
import { Button } from '@/shared/ui/button';
import { Input } from '@/shared/ui/input';
import { Search, UserPlus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    shown: number;
    total: number;
}>();

const emit = defineEmits<{
    invite: [];
}>();

const search = defineModel<string>('search', { required: true });

const { t } = useI18n();
</script>

<template>
    <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div class="relative w-full sm:w-64">
            <Search class="text-muted-foreground absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 transform" />
            <Input v-model="search" :placeholder="t('agents.chatters.search_placeholder')" class="ps-10" />
        </div>

        <div class="flex w-full items-center justify-between gap-3 sm:w-auto sm:justify-end">
            <div v-if="total > 0" class="text-muted-foreground text-sm">
                {{ t('agents.chatters.count', { filtered: shown, total }, total) }}
            </div>
            <Button size="sm" @click="emit('invite')">
                <UserPlus class="me-2 h-4 w-4" />
                {{ t('agents.chatters.invite_member') }}
            </Button>
        </div>
    </div>
</template>
