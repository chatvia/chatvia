/**
 * The translated name of a form type (lead, qualification, booking, generic)
 * for the agent's Forms page. Each key is a static `t()` call so the
 * missing-key check can see it.
 */
import { useI18n } from 'vue-i18n';

export function useFormTypeLabel() {
    const { t } = useI18n();

    function formTypeLabel(formType: string): string {
        switch (formType) {
            case 'lead':
                return t('agents.forms.type_lead');
            case 'qualification':
                return t('agents.forms.type_qualification');
            case 'booking':
                return t('agents.forms.type_booking');
            default:
                return t('agents.forms.type_generic');
        }
    }

    return { formTypeLabel };
}
