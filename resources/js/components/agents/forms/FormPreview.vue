<script setup lang="ts">
/**
 * Live preview of the form being edited, rendered with FormKit the same way
 * the chat renders it (title, description, then the fields).
 */
import { type FormDefinition, previewSchema } from '@/components/agents/forms/formDefinition';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FormKit, FormKitSchema } from '@formkit/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    form: FormDefinition;
}>();

const { t } = useI18n();

const schema = computed(() => previewSchema(props.form));
</script>

<template>
    <Card>
        <CardHeader
            ><CardTitle>{{ t('agents.forms.preview') }}</CardTitle></CardHeader
        >
        <CardContent>
            <div v-if="form.name || form.subtitle" class="mb-4 border-b pb-3">
                <p v-if="form.name" class="text-base font-semibold">{{ form.name }}</p>
                <p v-if="form.subtitle" class="text-muted-foreground mt-1 text-sm">{{ form.subtitle }}</p>
            </div>
            <FormKit type="form" :actions="false">
                <FormKitSchema :schema="schema" />
            </FormKit>
        </CardContent>
    </Card>
</template>
