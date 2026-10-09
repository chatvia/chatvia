<script setup lang="ts">
/** Setup → Behavior on the Command Bar tab: the keyboard trigger and where the bar opens. */
import SetupIconSelect from '@/components/agents/setup/SetupIconSelect.vue';
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import SetupSectionHeading from '@/components/agents/setup/SetupSectionHeading.vue';
import { useBehaviorOptions } from '@/composables/agents/setup/useSetupBehavior';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import { Keyboard, LayoutGrid } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { locked, behavior } = useSetupContext();
const { barForm: form, isSavingBar: isSaving, saveBar: save } = behavior;
const { triggerOptions, placementOptions } = useBehaviorOptions();
</script>

<template>
    <div class="space-y-6 p-6">
        <SetupSectionHeading :title="t('agents.setup.behavior.bar_title')" :description="t('agents.setup.behavior.bar_description')" />
        <div class="grid max-w-3xl gap-x-6 gap-y-5 sm:grid-cols-2">
            <SetupIconSelect
                id="bar-trigger"
                v-model="form.trigger"
                :label="t('agents.setup.behavior.keyboard_trigger')"
                :icon="Keyboard"
                :options="triggerOptions"
                :disabled="locked"
            />
            <SetupIconSelect
                id="bar-placement"
                v-model="form.placement"
                :label="t('agents.setup.behavior.placement')"
                :icon="LayoutGrid"
                :options="placementOptions"
                :disabled="locked"
            />
        </div>
        <SetupSaveFooter divided :saving="isSaving" @save="save" />
    </div>
</template>
