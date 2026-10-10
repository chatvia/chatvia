# Usage & billing

Chatvia is metered on **messages** (one assistant turn) and **ingestion credits** (parsing and embedding sources). Your plan caps the monthly allowance for the whole workspace; overage pricing varies by plan.

![SCREENSHOT — usage page](/screenshots/usage.png)

## Where to see your usage

**Settings → Usage** shows usage for the workspace, pooled across every agent and every teammate:

- **Messages this month** — assistant turns served across all agents.
- **Ingestion credits used** — parsing and embedding spend across all sources.
- **Storage** — total source content size for the workspace.
- **API calls** — public API requests.

The breakdown updates near-real-time. Charts at the bottom show the last 30 days so the workspace owner can spot spikes.

## Plans

Chatvia plans bundle, at the workspace level:

- A monthly message allowance shared across all agents.
- A monthly ingestion-credit allowance.
- A cap on storage.
- A cap on the number of agents.
- A cap on teammates (dashboard seats).
- A cap on chatters (end-users with access to /chat).
- Access to specific model tiers.

Compare the plans in the dashboard under **Billing → Plans**.

## Trial

New workspaces get a 7-day free trial with full feature access. After the trial the workspace owner is prompted to upgrade — if no plan is picked, agents go into read-only mode (chatters can browse past conversations but new questions return a "trial ended" message) until a plan is selected.

## Upgrading and downgrading

From **Billing**, click **Manage plan**. The workspace owner is taken to the secure billing portal to:

- Switch plans.
- Update payment methods.
- View and download invoices.
- Cancel.

Changes apply at the next billing cycle (downgrades) or immediately with proration (upgrades).

## How messages are counted

A "message" is one assistant turn — one response to one chatter question, on any surface (Hosted Chat, Widget, or Bar) and on any agent. System messages, retrieval, and tool calls inside a single turn don't count separately.

A few corner cases:

- **Streaming** — counted on completion, not per chunk.
- **Failed responses** — not counted if the agent never produced a reply (e.g. provider outage).
- **Human handoff replies** — not counted as messages (no LLM cost).
- **Playground** — not counted against the workspace message allowance, but they do consume API tokens upstream.

## How ingestion credits are counted

Ingestion credits are spent at upload time, proportional to:

- Document size and complexity.
- Number of pages crawled for web sources.
- Audio/video length.
- Re-ingestion (retraining a source costs the same as first ingestion).

Q&A and short text sources cost negligible credits.

## Overage

If the workspace exceeds its monthly allowance, behavior depends on plan:

- **Free / Starter** — agents are paused for chatters (with a friendly "limit reached" message) until next cycle or upgrade.
- **Pro / Business** — overage is billed at the per-message price on the invoice; nothing pauses.
- **Enterprise** — usually no caps; behavior set by contract.

Set up alerts in **Billing → Notifications** to get an email when the workspace crosses 75% / 90% / 100% of its monthly allowance.

## Invoices

Past invoices are available in the billing portal (link from **Billing**). Each shows:

- Subscription fee.
- Any overage charges.
- Tax (where applicable).
- A downloadable PDF.

## Failed payments

If a charge fails, Chatvia retries the card a few times over the following days. After several failures the workspace is locked until the payment method is updated.

Email notifications always go out before lockout — keep the billing email up to date.

## EU hosting and tax

Chatvia is hosted in the EU and bills in EUR (with currency conversion for non-EU workspaces). VAT is added for EU customers without a valid VAT ID. Update your VAT ID in the billing portal.
