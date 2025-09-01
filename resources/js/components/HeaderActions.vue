<template>
    <div class="flex items-center gap-2">
        <!-- Success Message -->
        <FormSuccessMessage
            v-if="showSuccessMessage"
            :show="showSuccessMessage"
            :message="successMessage"
            class="mr-2"
        />
        
        <!-- Error Message -->
        <div v-if="showErrorMessage && !showSuccessMessage" class="text-xs text-destructive mr-2">
            {{ errorMessage }}
        </div>
        
        <!-- Secondary Actions (like Restore Defaults) -->
        <template v-for="action in secondaryActions" :key="action.id">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="action.onClick"
                :disabled="action.disabled"
                class="gap-2"
            >
                <component :is="action.icon" v-if="action.icon" class="h-4 w-4" />
                <span class="hidden sm:inline">{{ action.label }}</span>
            </Button>
        </template>

        <!-- Primary Save Button with Pulse Badge -->
        <div class="relative">
            <Button
                :disabled="isSubmitting"
                size="sm"
                type="submit"
                :form="formId"
                class="gap-2 min-w-[100px]"
            >
                <component :is="saveIcon" v-if="!isSubmitting && saveIcon" class="h-4 w-4" />
                <div v-else-if="isSubmitting" class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                {{ isSubmitting ? 'Saving...' : saveLabel }}
            </Button>
            
            <!-- Pulse Badge Circle -->
            <div 
                v-if="hasUnsavedChanges && !isSubmitting"
                class="absolute -top-1 -right-1 h-3 w-3 bg-orange-500 rounded-full animate-pulse shadow-lg"
            >
                <div class="absolute inset-0 h-3 w-3 bg-orange-500 rounded-full animate-ping opacity-75"></div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormSuccessMessage from '@/components/FormSuccessMessage.vue';
import { Save } from 'lucide-vue-next';

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
    
    // Message props
    showSuccessMessage?: boolean;
    successMessage?: string;
    showErrorMessage?: boolean;
    errorMessage?: string;
    
    // Secondary actions
    secondaryActions?: SecondaryAction[];
}

const props = withDefaults(defineProps<Props>(), {
    isSubmitting: false,
    hasUnsavedChanges: false,
    saveLabel: 'Save',
    saveIcon: undefined,
    formId: undefined,
    showSuccessMessage: false,
    successMessage: 'Settings saved successfully',
    showErrorMessage: false,
    errorMessage: 'Please fix errors below',
    secondaryActions: () => []
});
</script>
