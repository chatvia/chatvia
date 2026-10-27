<script setup lang="ts">
/**
 * Setup → Behavior on the Widget tab: launcher position, window size, theme,
 * language, live-chat handoff, open-on-load (with delay) and the sites the
 * widget may run on. Live chat needs a license key: without one the switch
 * is locked and the saved value is kept.
 */
import SetupIconSelect from '@/components/agents/setup/SetupIconSelect.vue';
import SetupSaveFooter from '@/components/agents/setup/SetupSaveFooter.vue';
import SetupSectionHeading from '@/components/agents/setup/SetupSectionHeading.vue';
import SetupSwitchRow from '@/components/agents/setup/SetupSwitchRow.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { languageOptions, useBehaviorOptions } from '@/composables/agents/setup/useSetupBehavior';
import { useSetupContext } from '@/composables/agents/setup/useSetupContext';
import { useLicense } from '@/composables/useLicense';
import { Clock, Globe, Languages, MapPin, Maximize2, Palette } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    // code → name of every platform language (plus 'auto').
    availableLanguages: Record<string, string>;
}>();

const { t } = useI18n();
const { licensed } = useLicense();
const { locked, behavior } = useSetupContext();
const { widgetForm: form, isSavingWidget: isSaving, saveWidget: save, sitesText } = behavior;
const { positionOptions, windowSizeOptions, themeOptions } = useBehaviorOptions();
const languageChoices = computed(() => languageOptions(props.availableLanguages));
</script>

<template>
    <div class="space-y-6 p-6">
        <SetupSectionHeading :title="t('agents.setup.behavior.widget_title')" :description="t('agents.setup.behavior.widget_description')" />
        <div class="grid max-w-3xl gap-x-6 gap-y-5 sm:grid-cols-2">
            <SetupIconSelect
                id="widget-position"
                v-model="form.position"
                :label="t('agents.setup.behavior.position')"
                :icon="MapPin"
                :options="positionOptions"
                :disabled="locked"
            />
            <SetupIconSelect
                id="widget-size"
                v-model="form.window_size"
                :label="t('agents.setup.behavior.window_size')"
                :icon="Maximize2"
                :options="windowSizeOptions"
                :disabled="locked"
            />
            <SetupIconSelect
                id="widget-theme"
                v-model="form.theme"
                :label="t('agents.setup.behavior.theme')"
                :icon="Palette"
                :options="themeOptions"
                :disabled="locked"
            />
            <SetupIconSelect
                id="widget-language"
                v-model="form.language"
                :label="t('agents.setup.behavior.language')"
                :icon="Languages"
                :options="languageChoices"
                :disabled="locked"
            />
            <SetupSwitchRow
                id="widget-handoff"
                v-model="form.handoff_enabled"
                class="bg-muted/40 rounded-md border sm:col-span-2"
                :label="t('agents.setup.behavior.live_chat')"
                :hint="licensed ? t('agents.setup.behavior.live_chat_hint') : t('license.unavailable')"
                :disabled="locked || !licensed"
            />
            <SetupSwitchRow
                id="widget-show-on-load"
                v-model="form.show_on_load"
                class="bg-muted/40 rounded-md border sm:col-span-2"
                :label="t('agents.setup.behavior.open_on_load')"
                :disabled="locked"
            />
            <div v-if="form.show_on_load" class="space-y-1.5 sm:col-span-2">
                <Label for="widget-delay" class="text-sm font-medium">{{ t('agents.setup.behavior.delay_ms') }}</Label>
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3">
                        <Clock class="h-4 w-4" />
                    </div>
                    <Input id="widget-delay" type="number" min="0" v-model="form.show_on_load_delay" :disabled="locked" class="h-10 ps-9" />
                </div>
            </div>
            <div class="space-y-1.5 sm:col-span-2">
                <Label for="widget-sites" class="text-sm font-medium"
                    >{{ t('agents.setup.behavior.allowed_sites') }}
                    <span class="text-muted-foreground font-normal">{{ t('agents.setup.behavior.allowed_sites_hint') }}</span></Label
                >
                <div class="relative">
                    <div class="text-muted-foreground pointer-events-none absolute start-0 top-0 flex items-center ps-3 pt-2.5">
                        <Globe class="h-4 w-4" />
                    </div>
                    <Textarea
                        id="widget-sites"
                        v-model="sitesText"
                        rows="4"
                        :disabled="locked"
                        class="min-h-24 ps-9"
                        :placeholder="t('agents.setup.behavior.allowed_sites_placeholder')"
                    />
                </div>
                <p class="text-muted-foreground text-xs">{{ t('agents.setup.behavior.allowed_sites_help') }}</p>
            </div>
        </div>
        <SetupSaveFooter divided :saving="isSaving" @save="save" />
    </div>
</template>
