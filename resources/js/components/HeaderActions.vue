<template>
    <div class="flex items-center gap-2">
        <!-- Success ChatMessage -->
        <FormSuccessMessage v-if="showSuccessMessage" :show="showSuccessMessage" :message="successMessage" class="mr-2" />

        <!-- Error ChatMessage -->
        <div v-if="showErrorMessage && !showSuccessMessage" class="text-destructive mr-2 text-xs">
            {{ errorMessage }}
        </div>

        <!-- Secondary Actions (like Restore Defaults) -->
        <template v-for="action in secondaryActions" :key="action.id">
            <Button type="button" variant="outline" size="sm" @click="action.onClick" :disabled="action.disabled" class="gap-2">
                <component :is="action.icon" v-if="action.icon" class="h-4 w-4" />
                <span class="hidden sm:inline">{{ action.label }}</span>
            </Button>
        </template>

        <!-- Primary Save Button with Pulse Badge -->
        <div class="relative">
            <Button :disabled="isSubmitting" size="sm" type="submit" :form="formId" class="min-w-[100px] gap-2">
                <component :is="saveIcon" v-if="!isSubmitting && saveIcon" class="h-4 w-4" />
                <div v-else-if="isSubmitting" class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                {{ isSubmitting ? 'Saving...' : saveLabel }}
            </Button>

            <!-- Pulse Badge Circle -->
            <div
                v-if="hasUnsavedChanges && !isSubmitting"
                class="absolute -top-1 -right-1 h-3 w-3 animate-pulse rounded-full bg-orange-500 shadow-lg"
            >
                <div class="absolute inset-0 h-3 w-3 animate-ping rounded-full bg-orange-500 opacity-75"></div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';
import { Button } from '@/components/ui/button';

interface SecondaryAction {
    id: string;
    label: string;
    icon?: any;
    onClick: () => void;
    disabled?: boolean;
}

interface Props {
    // Save button props
    isSubmitting?: boolean;
    hasUnsavedChanges?: boolean;
    saveLabel?: string;
    saveIcon?: any;
    formId?: string;

    // ChatMessage props
    showSuccessMessage?: boolean;
    successMessage?: string;
    showErrorMessage?: boolean;
    errorMessage?: string;

    // Secondary actions
    secondaryActions?: SecondaryAction[];
}

const _props = withDefaults(defineProps<Props>(), {
    isSubmitting: false,
    hasUnsavedChanges: false,
    saveLabel: 'Save',
    saveIcon: undefined,
    formId: undefined,
    showSuccessMessage: false,
    successMessage: 'Settings saved successfully',
    showErrorMessage: false,
    errorMessage: 'Please fix errors below',
    secondaryActions: () => [],
});
</script>
