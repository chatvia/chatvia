<script setup lang="ts">
/**
 * Renders an inline form artifact (from RenderFormTool) as a FormKit form and
 * submits it back into the conversation. Transport is decoupled via an injected
 * `inlineFormSubmit` the chat surface provides (dashboard/hosted vs widget) —
 * the same decoupling the tool-approval card uses. The schema is built
 * server-side; we only render it (no raw HTML, labels/values are text nodes).
 */
import type { FormKitSchemaDefinition } from '@formkit/core';
import { FormKit, FormKitSchema } from '@formkit/vue';
import { CheckCircle2 } from 'lucide-vue-next';
import { inject, ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface FormArtifact {
    form_instance_id: string;
    title?: string;
    subtitle?: string;
    schema: FormKitSchemaDefinition;
    submit_label?: string;
    success_message?: string;
    status?: string;
}

type InlineFormSubmitter = (
    formInstanceId: string,
    fields: Record<string, unknown>,
    assistantMessageId?: string,
) => Promise<{ status: string; success_message?: string }>;

const props = defineProps<{ form: FormArtifact; messageId?: string }>();

const { t } = useI18n();
const submitter = inject<InlineFormSubmitter | null>('inlineFormSubmit', null);

const submitted = ref(props.form.status === 'submitted');
const successMessage = ref(props.form.success_message ?? t('chat.forms.default_success'));
const error = ref<string | null>(null);
const pending = ref(false);

async function onSubmit(fields: Record<string, unknown>): Promise<void> {
    if (submitted.value || pending.value) return;
    if (!submitter) {
        error.value = t('chat.forms.unavailable');
        return;
    }

    pending.value = true;
    error.value = null;
    try {
        const result = await submitter(props.form.form_instance_id, fields, props.messageId);
        successMessage.value = result.success_message ?? successMessage.value;
        submitted.value = true;
    } catch (e: unknown) {
        const status = (e as { response?: { status?: number } })?.response?.status;
        if (status === 409) {
            // Already submitted (e.g. after a reload) — treat as done.
            submitted.value = true;
        } else if (status === 410) {
            error.value = t('chat.forms.expired');
        } else {
            error.value = t('chat.forms.submit_failed');
        }
    } finally {
        pending.value = false;
    }
}
</script>

<template>
    <div class="bg-card my-2 rounded-lg border p-4">
        <div v-if="submitted" class="flex items-center gap-2 text-sm font-medium text-green-600">
            <CheckCircle2 class="h-5 w-5 shrink-0" />
            <span>{{ successMessage }}</span>
        </div>

        <div v-else>
            <div v-if="form.title || form.subtitle" class="mb-3 border-b pb-3">
                <p v-if="form.title" class="text-base font-semibold">{{ form.title }}</p>
                <p v-if="form.subtitle" class="text-muted-foreground mt-1 text-sm">{{ form.subtitle }}</p>
            </div>
            <FormKit type="form" :submit-label="form.submit_label ?? t('chat.forms.submit')" :disabled="pending" @submit="onSubmit">
                <FormKitSchema :schema="form.schema" />
            </FormKit>
            <p v-if="error" class="text-destructive mt-2 text-sm">{{ error }}</p>
        </div>
    </div>
</template>
