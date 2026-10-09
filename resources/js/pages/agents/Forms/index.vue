<script lang="ts" setup>
import FormEditor from '@/components/agents/forms/FormEditor.vue';
import type { FormDefinition, LeadField } from '@/components/agents/forms/formDefinition';
import FormList from '@/components/agents/forms/FormList.vue';
import FormPreview from '@/components/agents/forms/FormPreview.vue';
import { useAgentForms } from '@/composables/agents/forms/useAgentForms';
import AgentLayout from '@/layouts/AgentLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    agent: Domain.Agent.Data.Resources.Account.AgentResourceData;
    forms: FormDefinition[];
    leadFields: LeadField[];
}>();

const { t } = useI18n();

const { editing, saving, rewriting, startNew, edit, cancel, save, destroy, rewriteDescription } = useAgentForms(() => props.agent.external_id);
</script>

<template>
    <AgentLayout :agent="agent" :title="t('agents.forms.page_title')">
        <div class="mx-auto w-full max-w-5xl space-y-6">
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
                <FormPreview class="lg:col-span-2" :form="editing" />
            </div>
        </div>
    </AgentLayout>
</template>
