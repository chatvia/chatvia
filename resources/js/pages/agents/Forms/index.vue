<script lang="ts" setup>
// Forms need a license key: without one the page only explains the lock (the
// server sends no forms and refuses changes).
import FormEditor from '@/components/agents/forms/FormEditor.vue';
import type { FormDefinition, LeadField } from '@/components/agents/forms/formDefinition';
import FormList from '@/components/agents/forms/FormList.vue';
import FormPreview from '@/components/agents/forms/FormPreview.vue';
import LicenseNotice from '@/components/license/LicenseNotice.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useAgentForms } from '@/composables/agents/forms/useAgentForms';
import { useLicense } from '@/composables/useLicense';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { Info } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    forms: FormDefinition[];
    leadFields: LeadField[];
    previewAppearance: Domain.Form.Data.FormPreviewAppearanceData;
}>();

const { t } = useI18n();
const { licensed } = useLicense();

const { editing, saving, rewriting, startNew, edit, cancel, save, destroy, rewriteDescription } = useAgentForms(() => props.agent.external_id);
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.forms.page_title')" :description="t('agents.forms.page_description')">
        <div v-if="!licensed" class="mx-auto w-full max-w-5xl">
            <LicenseNotice :body="t('license.forms_body')" />
        </div>

        <div v-else class="mx-auto w-full max-w-5xl space-y-6">
            <!-- Hosted Chat does not render inline forms. -->
            <Alert>
                <Info class="h-4 w-4" />
                <AlertDescription>{{ t('agents.forms.surfaces_notice') }}</AlertDescription>
            </Alert>

            <FormList v-if="!editing" :agent-id="agent.external_id" :forms="forms" @create="startNew" @edit="edit" @delete="destroy" />

            <!-- Editor + live preview -->
            <div v-else-if="editing" class="grid gap-6 lg:grid-cols-5">
                <FormEditor
                    v-model:form="editing"
                    class="lg:col-span-3"
                    :lead-fields="leadFields"
                    :saving="saving"
                    :rewriting="rewriting"
                    @save="save"
                    @cancel="cancel"
                    @rewrite="rewriteDescription"
                />
                <FormPreview class="lg:col-span-2" :form="editing" :appearance="previewAppearance" />
            </div>
        </div>
    </AgentLayout>
</template>
