/**
 * The workspace's plan usage (agents, pages, minutes, links, …): how much of
 * each limit is used and how much is left. Source pages read it to warn before
 * an upload or a crawl would go over the plan.
 */
import { get } from './http';

export type Usage = Domain.Billing.Data.Resources.Usage.UsageResourceData;
export type UsageStats = Domain.Billing.Data.Resources.Usage.UsageStatsData;

export function fetchUsage(): Promise<Usage> {
    return get<Usage>(route('usage'));
}
