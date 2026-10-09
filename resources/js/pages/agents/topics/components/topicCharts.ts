/**
 * Colours and Chart.js options of the Topics page charts. Topics get colours
 * by position (the same index in the line chart, the pie and the sidebar
 * list), cycling through a fixed palette.
 */
import type { ChartOptions } from 'chart.js';

export const TOPIC_COLORS = [
    'rgb(139, 92, 246)', // Purple
    'rgb(59, 130, 246)', // Blue
    'rgb(16, 185, 129)', // Green
    'rgb(236, 72, 153)', // Pink
    'rgb(251, 146, 60)', // Orange
    'rgb(234, 179, 8)', // Yellow
    'rgb(239, 68, 68)', // Red
    'rgb(107, 114, 128)', // Gray
];

/** The colour of the topic at `index` in the totals or trends list. */
export function topicColor(index: number): string {
    return TOPIC_COLORS[index % TOPIC_COLORS.length];
}

// Fresh objects per chart: Chart.js may write into the option objects it is given.
const legend = () =>
    ({
        display: true,
        position: 'bottom',
        labels: {
            usePointStyle: true,
            padding: 20,
            font: {
                size: 12,
            },
            boxWidth: 8,
            boxHeight: 8,
        },
    }) as const;

const tooltipStyle = () => ({
    backgroundColor: 'rgba(0, 0, 0, 0.8)',
    padding: 12,
    titleFont: {
        size: 14,
    },
    bodyFont: {
        size: 13,
    },
});

export const topicLineOptions: ChartOptions<'line'> = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        mode: 'index',
        intersect: false,
    },
    plugins: {
        legend: legend(),
        tooltip: {
            ...tooltipStyle(),
            displayColors: true,
            callbacks: {
                label: function (context) {
                    return `${context.dataset.label}: ${context.parsed.y}`;
                },
            },
        },
    },
    scales: {
        x: {
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
            },
        },
        y: {
            beginAtZero: true,
            grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)',
            },
            ticks: {
                font: {
                    size: 11,
                },
                precision: 0,
            },
        },
    },
};

/** Pie options; `total` is read when a tooltip opens, so it follows reloads. */
export function topicPieOptions(total: () => number): ChartOptions<'pie'> {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: legend(),
            tooltip: {
                ...tooltipStyle(),
                callbacks: {
                    label: function (context) {
                        const label = context.label || '';
                        const value = context.parsed || 0;
                        const sum = total();
                        const percentage = sum > 0 ? ((value / sum) * 100).toFixed(1) : 0;
                        return `${label}: ${value} (${percentage}%)`;
                    },
                },
            },
        },
    };
}
