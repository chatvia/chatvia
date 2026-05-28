<script setup lang="ts">
defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [boolean] }>();

// Unique group name so two instances (e.g. the sidebar switcher + the teams
// page) mounted at once don't share native radio grouping.
const groupName = `team-access-${Math.random().toString(36).slice(2, 9)}`;

const options: { full: boolean; title: string; description: string }[] = [
    {
        full: true,
        title: 'Control panel access',
        description: 'Members can sign in to the control panel to manage agents, knowledge, and settings.',
    },
    {
        full: false,
        title: 'Chat only',
        description: "Members can only chat with this team's agents — they never see the control panel.",
    },
];
</script>

<template>
    <div class="grid gap-2" role="radiogroup" aria-label="Team access">
        <label
            v-for="opt in options"
            :key="opt.title"
            :class="[
                'flex cursor-pointer items-start gap-3 rounded-md border p-3 transition-colors',
                modelValue === opt.full ? 'border-primary ring-primary ring-1' : 'hover:bg-muted/50',
            ]"
        >
            <input
                type="radio"
                :name="groupName"
                class="accent-primary mt-1 size-4 shrink-0"
                :checked="modelValue === opt.full"
                @change="emit('update:modelValue', opt.full)"
            />
            <div class="grid gap-0.5">
                <span class="text-sm font-medium leading-none">{{ opt.title }}</span>
                <span class="text-muted-foreground text-sm">{{ opt.description }}</span>
            </div>
        </label>
    </div>
</template>
