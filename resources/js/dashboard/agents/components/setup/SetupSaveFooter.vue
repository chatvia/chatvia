<script setup lang="ts">
/**
 * The Save button under a Setup section. Hidden for view-only users; shows
 * "Saving…" while the section's request runs. As a submit button it submits
 * the surrounding form; otherwise it emits `save`.
 */
import { Button } from '@/shared/ui/button';
import { useSetupContext } from '@/dashboard/agents/composables/setup/useSetupContext';
import { Save } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        saving: boolean;
        disabled?: boolean;
        submit?: boolean;
        // Draw a divider line above the button.
        divided?: boolean;
    }>(),
    { disabled: false, submit: false, divided: false },
);

const emit = defineEmits<{ save: [] }>();

const { t } = useI18n();
const { locked } = useSetupContext();

const onClick = (): void => {
    if (!props.submit) emit('save');
};
</script>

<template>
    <div v-if="!locked" :class="{ 'border-t pt-4': divided }">
        <Button :type="submit ? 'submit' : 'button'" size="sm" :disabled="disabled || saving" @click="onClick">
            <Save class="me-2 h-4 w-4" />
            {{ saving ? t('agents.setup.saving') : t('common.save') }}
        </Button>
    </div>
</template>
