# Usage & billing

Chatvia is metered on **messages** (one assistant turn) and **ingestion credits** (parsing and embedding sources). Your plan caps the monthly allowance; overage pricing varies by plan.

![SCREENSHOT — usage page](/screenshots/usage.png)

## Where to see your usage

**Settings → Usage** shows:

- **Messages this month** — how many assistant turns have been served.
- **Ingestion credits used** — what you've spent on parsing and embedding.
- **Storage** — total source content size.
- **API calls** — public API requests.

The breakdown updates near-real-time. Charts at the bottom show the last 30 days so you can spot spikes.

## Plans

Chatvia plans bundle:

- A monthly message allowance.
- A monthly ingestion-credit allowance.
- A cap on storage.
- A cap on number of agents.
- A cap on team members.
- Access to specific model tiers.

Plans are tiered (Free trial → Starter → Pro → Business → Enterprise). Compare on the [pricing page](https://chatvia.ai/pricing) on the marketing site.

## Trial

New accounts get a 7-day free trial with full feature access. After the trial you'll be prompted to upgrade — if you don't, your agents go into read-only mode (visitors can browse past conversations but new questions return a "trial ended" message) until you pick a plan.

## Upgrading and downgrading

From **Billing**, click **Manage plan**. You'll go to the secure billing portal where you can:

- Switch plans.
- Update payment methods.
- View and download invoices.
- Cancel.

Changes apply at the next billing cycle (downgrades) or immediately with proration (upgrades).

## How messages are counted

A "message" is one assistant turn — one response to one visitor question. System messages, retrieval, and tool calls inside a single turn don't count separately.

A few corner cases:

- **Streaming** — counted on completion, not per chunk.
- **Failed responses** — not counted if the agent never produced a reply (e.g. provider outage).
- **Human handoff replies** — not counted as messages (no LLM cost).
- **Playground** — not counted against your visitor allowance, but they do consume API tokens upstream.

## How ingestion credits are counted

Ingestion credits are spent at upload time, proportional to:

- Document size and complexity.
- Number of pages crawled for web sources.
- Audio/video length.
- Re-ingestion (retraining a source costs the same as first ingestion).

Q&A and short text sources cost negligible credits.

## Overage

If you exceed your monthly allowance, behavior depends on plan:

- **Free / Starter** — agents are paused for visitors (with a friendly "limit reached" message) until next cycle or upgrade.
- **Pro / Business** — overage is billed at the per-message price on the invoice; nothing pauses.
- **Enterprise** — usually no caps; behavior set by contract.

Set up alerts in **Billing → Notifications** to get an email when you cross 75% / 90% / 100% of your monthly allowance.

## Invoices

Past invoices are available in the billing portal (link from **Billing**). Each shows:

- Subscription fee.
- Any overage charges.
- Tax (where applicable).
- A downloadable PDF.

## Failed payments

If a charge fails, Chatvia retries the card a few times over the following days. After several failures the account is locked until you update the payment method.

You'll always get email notifications before lockout — keep your billing email up to date.

## EU hosting and tax

Chatvia is hosted in the EU and bills in EUR (with currency conversion for non-EU accounts). VAT is added for EU customers without a valid VAT ID. Update your VAT ID in the billing portal.
