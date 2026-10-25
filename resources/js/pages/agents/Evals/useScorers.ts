/**
 * Icon, label and help text for each eval scorer type. The case table and the
 * case dialog show the same scorer in several places, so the lookups live here.
 * Unknown types fall back to the raw type name (label) or nothing (help, icon).
 */
import { Braces, Equal, Quote, Regex, Scale, Search, Wrench } from 'lucide-vue-next';
import type { Component } from 'vue';
import { useI18n } from 'vue-i18n';

const SCORER_ICONS: Record<string, Component> = {
    contains: Search,
    regex: Regex,
    exact: Equal,
    json_schema: Braces,
    llm_judge: Scale,
    tool_call: Wrench,
    citations: Quote,
};

// Shorter names used on the run detail page, where space is tighter.
const SHORT_LABEL_KEYS: Record<string, string> = {
    contains: 'agents.evals.scorers.contains',
    regex: 'agents.evals.scorers.regex',
    exact: 'agents.evals.scorers.exact',
    json_schema: 'agents.evals.scorers.json_schema',
    llm_judge: 'agents.evals.scorers.llm_judge',
    tool_call: 'agents.evals.scorers.tool_call',
    citations: 'agents.evals.scorers.citations',
};

/** The lucide icon for a scorer type, or undefined for an unknown type. */
export const scorerIcon = (type: string): Component | undefined => SCORER_ICONS[type];

export function useScorers() {
    const { t } = useI18n();

    const scorerLabel = (type: string): string => {
        const key = `agents.evals.index.scorers.${type}.label`;
        return t(key) !== key ? t(key) : type;
    };

    const scorerHelp = (type: string): string => {
        const key = `agents.evals.index.scorers.${type}.help`;
        return t(key) !== key ? t(key) : '';
    };

    const scorerShortLabel = (type: string): string => (SHORT_LABEL_KEYS[type] ? t(SHORT_LABEL_KEYS[type]) : type);

    return { scorerIcon, scorerLabel, scorerHelp, scorerShortLabel };
}
