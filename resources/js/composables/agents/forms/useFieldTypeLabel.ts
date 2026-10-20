/**
 * The translated name of a form field's input type (text, email, select…) for
 * the form editor. Each key is a static `t()` call so the missing-key check
 * can see it.
 */
import { useI18n } from 'vue-i18n';

export function useFieldTypeLabel() {
    const { t } = useI18n();

    function fieldTypeLabel(type: string): string {
        switch (type) {
            case 'email':
                return t('agents.forms.field_type_email');
            case 'tel':
                return t('agents.forms.field_type_tel');
            case 'number':
                return t('agents.forms.field_type_number');
            case 'textarea':
                return t('agents.forms.field_type_textarea');
            case 'select':
                return t('agents.forms.field_type_select');
            case 'radio':
                return t('agents.forms.field_type_radio');
            case 'checkbox':
                return t('agents.forms.field_type_checkbox');
            case 'multicheckbox':
                return t('agents.forms.field_type_multicheckbox');
            case 'date':
                return t('agents.forms.field_type_date');
            default:
                return t('agents.forms.field_type_text');
        }
    }

    return { fieldTypeLabel };
}
