/**
 * The translated name of a website status ("Crawling", "Trained", …), as shown
 * in the status tooltips of the Web sources page.
 */
import type { WebsiteStatus } from '@/dashboard/agents/api/webSources';
import { useI18n } from 'vue-i18n';

const LABEL_KEYS: Record<WebsiteStatus, string> = {
    pending: 'agents.sources.web.status.pending',
    crawling: 'agents.sources.web.status.crawling',
    crawled: 'agents.sources.web.status.crawled',
    ingesting: 'agents.sources.web.status.training',
    ingested: 'agents.sources.web.status.trained',
    failed: 'agents.sources.web.status.failed',
};

export function useWebsiteStatusLabel() {
    const { t } = useI18n();

    return (status: WebsiteStatus): string => (LABEL_KEYS[status] ? t(LABEL_KEYS[status]) : status);
}
