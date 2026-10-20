/**
 * Types and pure helpers for the agent's form builder: the form and field
 * shapes the Forms page edits, the allowed form and field types, the
 * "value|label" option list format, the fields the live preview shows and the payload
 * sent when a form is saved.
 */
// Type aliases (not interfaces) where a shape ends up in Inertia visit data,
// which needs an index signature.
export type FormFieldOption = {
    value: string;
    label: string;
};

export interface FormField {
    key: string;
    label: string;
    type: string;
    required: boolean;
    placeholder?: string;
    options?: FormFieldOption[];
    /** Editor-only: the contact field this answer maps to. Saved as `lead_field_map`. */
    leadField?: string;
}

export interface FormDefinition {
    external_id?: string | null;
    slug?: string | null;
    name: string;
    subtitle: string | null;
    form_type: string;
    schema: FormField[];
    lead_field_map: Record<string, string> | null;
    success_message: string | null;
    agent_instructions: string | null;
    is_active: boolean;
    submissions_count: number;
}

/** A contact attribute (name, email, …) a form field can be mapped to. */
export interface LeadField {
    key: string;
    label: string;
}

export const FIELD_TYPES = ['text', 'email', 'tel', 'number', 'textarea', 'select', 'radio', 'checkbox', 'multicheckbox', 'date'];
export const FORM_TYPES = ['lead', 'qualification', 'booking', 'generic'];
export const OPTION_TYPES = ['select', 'radio', 'multicheckbox'];

/** Only lead-shaped forms map their answers onto contact fields. */
export function isLeadBoundType(formType: string): boolean {
    return ['lead', 'qualification'].includes(formType);
}

export function blankForm(): FormDefinition {
    return {
        name: '',
        subtitle: null,
        form_type: 'lead',
        schema: [{ key: 'name', label: 'Name', type: 'text', required: true, leadField: 'name' }],
        lead_field_map: null,
        success_message: null,
        agent_instructions: null,
        is_active: true,
        submissions_count: 0,
    };
}

/** A copy of a saved form for the editor, with each field's `leadField` read back from the saved map. */
export function editableCopy(form: FormDefinition): FormDefinition {
    const map = form.lead_field_map ?? {};

    return {
        ...form,
        schema: form.schema.map((field) => ({ ...field, leadField: map[field.key] })),
    };
}

/** A snake_case key from a label that does not clash with the taken keys. */
export function slugKey(label: string, taken: string[]): string {
    const base =
        (label || 'field')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || 'field';
    let key = base;
    let i = 1;
    while (taken.includes(key)) key = `${base}_${++i}`;
    return key;
}

/** One option per line; "value|label" sets a label that differs from the value. */
export function parseOptions(text: string): FormFieldOption[] {
    return text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [value, label] = line.split('|').map((part) => part.trim());
            return { value: value, label: label || value };
        });
}

export function optionsText(field: FormField): string {
    return (field.options ?? []).map((option) => (option.label === option.value ? option.value : `${option.value}|${option.label}`)).join('\n');
}

/** The fields the live preview can show: those with both a label and a key. */
export function previewFields(form: FormDefinition): FormField[] {
    return form.schema.filter((field) => field.label && field.key);
}

export type FormSavePayload = {
    name: string;
    subtitle: string | null;
    form_type: string;
    success_message: string | null;
    agent_instructions: string | null;
    is_active: boolean;
    schema: {
        key: string;
        label: string;
        type: string;
        required: boolean;
        placeholder: string | null;
        options: FormFieldOption[] | null;
    }[];
    lead_field_map: Record<string, string> | null;
};

/** The body for the store / update routes: editor-only `leadField`s fold into `lead_field_map`. */
export function savePayload(form: FormDefinition): FormSavePayload {
    const leadMap: Record<string, string> = {};
    if (isLeadBoundType(form.form_type)) {
        for (const field of form.schema) {
            if (field.leadField) leadMap[field.key] = field.leadField;
        }
    }

    return {
        name: form.name,
        subtitle: form.subtitle,
        form_type: form.form_type,
        success_message: form.success_message,
        agent_instructions: form.agent_instructions,
        is_active: form.is_active,
        schema: form.schema.map((field) => ({
            key: field.key,
            label: field.label,
            type: field.type,
            required: field.required,
            placeholder: field.placeholder ?? null,
            options: OPTION_TYPES.includes(field.type) ? (field.options ?? []) : null,
        })),
        lead_field_map: Object.keys(leadMap).length ? leadMap : null,
    };
}
