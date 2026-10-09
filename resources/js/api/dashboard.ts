/**
 * Dashboard home API: the workspace's headline numbers (agents, messages,
 * conversations, sessions), the message-credit panel and the daily reply
 * volume for the activity chart. Mirrors Domain\Analytics\Data\Dashboard.
 */
import { get } from './http';

/** AI replies on one day, with the short label the activity chart prints. */
export interface DailyVolume {
    date: string;
    label: string;
    value: number;
}

/** AI replies this calendar month vs last month, and the change in percent. */
export interface MessagesTrend {
    this_month: number;
    last_month: number;
    percent_change: number;
}

/** Start and end of the workspace's active billing cycle. */
export interface BillingCycle {
    started_at: string;
    ends_at: string;
}

/** The "Messages this cycle" panel: replies used, what is left, the limit and the trend. */
export interface MessagesPanel {
    total: number;
    available: number;
    limit: number;
    cycle: BillingCycle | null;
    trend: MessagesTrend;
}

/** Distinct knowledge sources attached to the workspace's agents. */
export interface KnowledgeSourcesStats {
    files: number;
    websites: number;
    crawled_pages: number;
    total: number;
}

/** One plan quota: how much is used, the limit, and what remains. */
export interface QuotaUsage {
    used: number;
    limit: number;
    remaining: number;
}

/** The plan-usage panel: the plan name and each quota. */
export interface PlanUsage {
    plan: string;
    messages: QuotaUsage;
    pages: QuotaUsage;
    minutes: QuotaUsage;
    links: QuotaUsage;
}

/** Response of `dashboard.stats` (Domain\Analytics\Data\Dashboard\DashboardStatsData). */
export interface DashboardStats {
    agents: { total: number };
    messages: MessagesPanel;
    conversations: number;
    active_sessions: number;
    knowledge_sources: KnowledgeSourcesStats;
    team: { members: number };
    weekly_usage: DailyVolume[];
    monthly_usage: DailyVolume[];
    usage: PlanUsage;
}

/** Everything the dashboard home page shows, for the viewer's workspace and current team. */
export function fetchDashboardStats(): Promise<DashboardStats> {
    return get<DashboardStats>(route('dashboard.stats'));
}
