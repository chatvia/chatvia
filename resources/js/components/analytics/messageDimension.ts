/**
 * Shared shapes for the per-message dimension pages (Tone, Sentiment). A page
 * describes its buckets once — line series, pie slices and summary cards with
 * their labels and colours — and MessageDimensionAnalytics renders them.
 */
import type { DimensionDay, DimensionTotals } from '@/api/analytics';
import type { BadgeVariants } from '@/components/ui/badge';

/** A breakdown with the endpoint-specific keys (`toneByDate`, `totalTones`, …) normalised. */
export interface DimensionBreakdown<Bucket extends string> {
    byDate: DimensionDay<Bucket>[];
    totals: DimensionTotals<Bucket>;
}

/** Point styling of a line series; the two pages draw their points differently. */
export interface DimensionPointStyle {
    pointRadius: number;
    pointBorderColor?: string;
    pointBorderWidth?: number;
}

/** One line on the trend chart. `color` is an `rgb(…)` string; the fill is derived from it. */
export interface DimensionLineSeries<Bucket extends string> {
    key: Bucket | 'unspecified';
    label: string;
    color: string;
    point: DimensionPointStyle;
}

/** One slice of the pie chart. */
export interface DimensionPieSlice<Bucket extends string> {
    key: Bucket | 'unspecified';
    label: string;
    color: string;
}

/** One summary card above the chart: count and share of a bucket. */
export interface DimensionSummaryCard<Bucket extends string> {
    key: Bucket | 'unspecified';
    label: string;
    badgeVariant?: BadgeVariants['variant'];
    badgeClass?: string;
}

/** The translated texts that differ between the dimension pages. */
export interface DimensionCopy {
    disabledTitle: string;
    disabledDescription: string;
    blockedReason: string;
    liveAnalytics: string;
    loadError: string;
    tryAgain: string;
    chartTitle: string;
    graph: string;
    pieChart: string;
}

/** `rgb(r, g, b)` → `rgba(r, g, b, alpha)`, for the translucent fill under a line. */
export function translucent(color: string, alpha = 0.1): string {
    return color.replace('rgb', 'rgba').replace(')', `, ${alpha})`);
}
