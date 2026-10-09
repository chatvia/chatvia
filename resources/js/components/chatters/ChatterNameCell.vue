<script setup lang="ts">
// First column of a chatter table: avatar (photo or initials) and name.
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { TableCell } from '@/components/ui/table';
import { getInitials } from '@/composables/useInitials';

defineProps<{
    name: string;
    avatar: string | null;
}>();
</script>

<template>
    <TableCell class="font-medium">
        <div class="flex items-center gap-3">
            <Avatar class="size-8">
                <AvatarImage v-if="avatar" :src="avatar" :alt="name" />
                <AvatarFallback class="text-xs">{{ getInitials(name) }}</AvatarFallback>
            </Avatar>
            <span>{{ name }}</span>
        </div>
    </TableCell>
</template>
