/**
 * Website knowledge sources of an agent: the websites it crawls, the pages
 * ("links") discovered on each website, and removing, unlinking or retraining
 * them. Adding a website and starting training are Inertia visits and stay in
 * the components.
 */
import trainedWebsites from '@/routes/agents/sources/trained-websites';
import websites from '@/routes/agents/sources/websites';
import { del, get, post } from './http';

/** Lifecycle of a website: discovery (pending → crawling → crawled), then training (ingesting → ingested). */
export type WebsiteStatus = 'pending' | 'crawling' | 'crawled' | 'ingesting' | 'ingested' | 'failed';

/** Training state of one discovered page. */
export type WebsiteLinkStatus = 'pending' | 'ingesting' | 'ingested' | 'failed';

/** The path filters the website was crawled with. */
export interface WebsiteCrawlOptions {
    include_paths?: string;
    exclude_paths?: string;
    robots?: boolean;
}

/** A website as the agent's website list returns it. */
export interface WebsiteRecord {
    id: number;
    url: string;
    status: WebsiteStatus;
    created_at: string;
    options: WebsiteCrawlOptions | null;
    urls_count: number;
    agents_count: number;
}

/** A discovered page as the paginated link list returns it. */
export interface WebsiteLinkRecord {
    id: number;
    source_website_id: number;
    url: string;
    status: WebsiteLinkStatus | null;
    created_at: string | null;
    crawled_at: string | null;
}

/**
 * One page of links. The endpoint uses simple pagination (50 per page), so
 * there is no total or last page; `total` and `last_page` stay optional.
 */
export interface WebsiteLinkPage {
    data: WebsiteLinkRecord[];
    current_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    to: number | null;
    total?: number;
    last_page?: number;
}

export function listWebsites(agentId: string): Promise<WebsiteRecord[]> {
    return get<WebsiteRecord[]>(websites.index.url(agentId));
}

/** A page of a website's links, optionally filtered by a URL search. */
export function listWebsiteLinks(agentId: string, websiteId: number, page: number, search: string): Promise<WebsiteLinkPage> {
    const params: { page: number; q?: string } = { page };
    if (search.trim()) {
        params.q = search.trim();
    }

    return get<WebsiteLinkPage>(websites.links.index.url({ agent: agentId, website: websiteId }), { params });
}

/** Deletes the website everywhere, for every agent that uses it. */
export function deleteWebsite(agentId: string, websiteId: number): Promise<void> {
    return del(websites.destroy.url({ agent: agentId, website: websiteId }));
}

/** Removes a shared website from this agent only. */
export function unlinkWebsite(agentId: string, websiteId: number): Promise<void> {
    return post(trainedWebsites.unlink.url({ agent: agentId, websiteId }));
}

export function deleteWebsiteLink(agentId: string, websiteId: number, linkId: number): Promise<void> {
    return del(websites.links.destroy.url({ agent: agentId, website: websiteId, link: linkId }));
}

export function deleteWebsiteLinks(agentId: string, websiteId: number, linkIds: number[]): Promise<void> {
    return post(websites.links.bulkDestroy.url({ agent: agentId, website: websiteId }), { link_ids: linkIds });
}

/** Scrapes and trains one page again. */
export function retrainWebsiteLink(agentId: string, websiteId: number, linkId: number): Promise<void> {
    return post(websites.links.retrain.url({ agent: agentId, website: websiteId, link: linkId }));
}
