/**
 * Dashboard home API: the workspace's headline numbers (agents, messages,
 * conversations, sessions), the messages panel and the daily reply volume for
 * the activity chart. Mirrors Domain\Analytics\Data\Dashboard.
 */
import { stats } from '@/routes/dashboard';
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

/**
 * The "Messages this month" panel: replies this month, the workspace's monthly
 * message limit and what is left of it (both null without a limit), and the trend.
 */
export interface MessagesPanel {
    total: number;
    limit: number | null;
    available: number | null;
    trend: MessagesTrend;
}

/** Distinct knowledge sources attached to the workspace's agents. */
export interface KnowledgeSourcesStats {
    files: number;
    websites: number;
    crawled_pages: number;
    total: number;
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
}

/** Everything the dashboard home page shows, for the viewer's workspace and current team. */
export function fetchDashboardStats(): Promise<DashboardStats> {
    return get<DashboardStats>(stats.url());
}
