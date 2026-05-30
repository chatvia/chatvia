# Analytics

Per-agent analytics surface what your visitors are asking, how they feel, and how your agent is performing. Open an agent and click **Analytics** on the sidebar.

![SCREENSHOT — analytics overview](/screenshots/analytics.png)

## The five views

The analytics section has its own sub-nav:

- **Metrics** — volume, response time, completion rate.
- **Sentiment** — positive / negative / neutral breakdown of conversations.
- **Tone** — professional, casual, friendly, frustrated, etc.
- **Topics** — auto-clustered themes.
- **Sources** — which sources are pulling their weight in retrieval.

All views accept the same **date range** filter — last 7 days, last 30, last quarter, custom.

## Metrics

The headline numbers:

- **Conversations** — total this period, with a trend chart.
- **Messages** — assistant turns served.
- **Avg messages per conversation** — proxy for depth of engagement.
- **Avg response time** — how fast the agent answered.
- **Completion rate** — share of conversations that reached at least one assistant reply (vs. visitor bouncing on the empty state).
- **Handoff rate** — share that escalated to a human.

Spikes are usually traffic — a new embed, a viral mention. Dips can be a broken widget, a billing pause, or a model outage.

## Sentiment

![SCREENSHOT — sentiment view](/screenshots/analytics-sentiment.png)

Conversations are auto-classified at end as **positive**, **neutral**, or **negative**. The view shows:

- Stacked bar chart over time.
- Sample conversations for each class (so you can read the actual messages).
- Per-topic sentiment — useful for spotting "billing questions skew negative".

## Tone

Same shape as sentiment, but for *visitor* tone. Surfaces things like:

- Spikes in frustrated tone after a release.
- Casual tone dominating on consumer agents, professional on B2B.

## Topics

![SCREENSHOT — topics view](/screenshots/analytics-topics.png)

Chatvia clusters conversations into topics. Two layers:

- **Automatic clusters** — discovered from the data ("password reset", "shipping delays", "API limits").
- **Manual topics** — you define them in the **Topics** management page; conversations are tagged against the closest match.

Both are visible in the topics view, with counts, samples, and a "top words" cloud.

::: tip Use manual topics for product reporting
Auto-clustered names drift. If you want stable analytics ("how many billing questions per week?"), define a manual **Billing** topic and let the agent classify against it.
:::

## Sources

A breakdown of which sources have been retrieved into responses, ranked. Use this to:

- **Spot dead weight** — sources with zero retrievals in 30 days are not earning their storage. Consider deleting.
- **Find gaps** — if visitors are asking about a feature and no source surfaces, you're missing content.
- **Prioritize improvement** — the most-retrieved sources are your most-read; make them excellent.

## Country and language

Most views are filterable by **country** (derived from visitor IP) and **language** (detected from conversation text). Useful for multi-market agents — see whether your DE traffic and your US traffic ask different things.

## Exporting

Each view has an **Export** button that downloads the filtered data as CSV or JSON. Pipe into your own BI stack if you want to combine it with non-Chatvia metrics (product usage, support tickets).

## How accurate are these classifications?

Sentiment, tone, and topic classifications are done by an LLM running offline on each conversation after it ends. Accuracy is good but not perfect — expect ~85–90% agreement with a human reviewer. Don't make billing decisions off a single conversation; use the aggregated trends.

## Privacy

Analytics aggregates derived metadata, not message content. The drill-down samples show actual messages — and they're subject to your normal **retention** settings. Set a short retention window if you want analytics without long-term message storage.
