/**
 * Agent analytics API: the overview numbers, the per-day tone and sentiment
 * breakdowns, the per-agent analytics switches and the country names the
 * world map shows. Pages under resources/js/pages/agents (Analytics, Tone,
 * Sentiment, Topics) read through these functions instead of calling axios.
 */
import analytics from '@/routes/agents/analytics';
import sentiment from '@/routes/agents/sentiment';
import settings from '@/routes/agents/settings';
import tone from '@/routes/agents/tone';
import countries from '@/routes/countries';
import { get, post } from './http';

/** The overview numbers of one agent (Domain\Agent\Data\Resources\Stats\StatsResourceData). */
export type AgentStats = Domain.Agent.Data.Resources.Stats.StatsResourceData;

/** Optional date range for the overview; a missing bound lets the server pick its default. */
export interface AgentStatsRange {
    start_date?: string;
    end_date?: string;
}

/** An analytics feature that can be switched on per agent (the `analytics` settings group). */
export type AnalyticsFeature = 'tone' | 'sentiment' | 'topics';

/** The tone values the analysis assigns to a visitor message (Domain\Analytics\Enums\ToneEnum). */
export type ToneBucket = 'neutral' | 'frustrated' | 'urgent' | 'confused' | 'satisfied';

/** The sentiment values the analysis assigns to a visitor message (Domain\Analytics\Enums\SentimentEnum). */
export type SentimentBucket = 'positive' | 'negative' | 'neutral';

/**
 * One day of a message-dimension breakdown: the count per analysed value, plus
 * `unspecified` for visitor messages that have no value (yet).
 */
export type DimensionDay<Bucket extends string> = { date: string } & Record<Bucket | 'unspecified', number>;

/** Range totals of a message-dimension breakdown; `total` counts every visitor message. */
export type DimensionTotals<Bucket extends string> = Record<Bucket | 'unspecified' | 'total', number>;

/** Response of `agents.tone.data` (AgentToneDataController). */
export interface ToneBreakdown {
    toneByDate: DimensionDay<ToneBucket>[];
    totalTones: DimensionTotals<ToneBucket>;
}

/** Response of `agents.sentiment.data` (AgentSentimentDataController). */
export interface SentimentBreakdown {
    sentimentByDate: DimensionDay<SentimentBucket>[];
    totalSentiments: DimensionTotals<SentimentBucket>;
}

/** One entry of `countries.list`: ISO code and translated name. */
export interface CountryName {
    code: string;
    name: string;
}

/** The agent's overview numbers for the given date range. */
export function fetchAgentStats(agentId: string, range: AgentStatsRange): Promise<AgentStats> {
    return get<AgentStats>(analytics.data.url(agentId), { params: range });
}

/** Switches one analytics feature on for the agent. */
export function enableAnalyticsFeature(agentId: string, feature: AnalyticsFeature): Promise<void> {
    return post(settings.update.url({ agent: agentId, group: 'analytics' }), { [feature]: true });
}

/** Visitor tone per day and in total. */
export function fetchToneBreakdown(agentId: string): Promise<ToneBreakdown> {
    return get<ToneBreakdown>(tone.data.url(agentId));
}

/** Visitor sentiment per day and in total. */
export function fetchSentimentBreakdown(agentId: string): Promise<SentimentBreakdown> {
    return get<SentimentBreakdown>(sentiment.data.url(agentId));
}

/** Every country with its translated name, sorted by name. */
export function fetchCountryNames(): Promise<CountryName[]> {
    return get<CountryName[]>(countries.list.url());
}
