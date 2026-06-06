# Leads

A **lead** is contact information a customer shared with one of your agents — usually a name and email, sometimes also phone, company, or location. Chatvia has a built-in lead form that your agent shows at a configurable moment (on open, before chat, after N messages). Leads are captured across every surface — Hosted Chat, Widget, and Bar — and roll up into one workspace-wide pipeline your team can work from.

![SCREENSHOT — leads page](/screenshots/chat-leads.png)

## Enabling lead capture

Open an agent, go to **Leads** in the sidebar, then **Settings**:

| Field | Notes |
| --- | --- |
| **Enabled** | Master switch. When off, no form is shown. |
| **Trigger** | When to show — `on_open`, `before_chat`, `after_n_messages`. |
| **Required fields** | Which of name / email / phone / company / job title / location / message are required vs. optional. |
| **Heading and copy** | Customize the form text. |
| **Success message** | What the customer sees after submitting. |

The form appears inline in every surface — customers don't navigate anywhere. If they skip it (when fields are optional), the chat continues without a lead.

## Where leads show up

A submitted lead lands in **two places**:

- **Leads** page on the agent — a sortable, filterable table your team can work from.
- **Conversation detail** in [Chat logs](/guide/chat/logs) — every conversation with a lead shows the lead summary at the top.

Both are linked: clicking a lead opens its conversation, and the conversation links back to the lead.

## Lead fields

| Field | Notes |
| --- | --- |
| **Name** | Free text. |
| **Email** | Validated client-side. |
| **Phone** | Free text — no validation. |
| **Job title, company, location** | Optional context fields. |
| **Message** | Optional textarea for "what's your question?" |
| **Page URL** | Captured from the host page. |
| **Referrer URL** | Captured from `document.referrer`. |
| **IP, user agent, locale** | Captured automatically. |

## Exporting leads

The **Export CSV** button downloads the filtered list. Useful for piping into your CRM nightly.

For real-time delivery, wire up a [function](/guide/tools/functions) called `create_crm_lead` that the agent calls when it captures one — or pull from the API on a cron. Functions are workspace-level, so one CRM connector covers every agent your team runs.

## Notifications

In **Leads → Settings**, you can subscribe one or more teammate email addresses to receive an alert whenever a new lead is captured. Useful for low-volume agents (your sales team gets a Slack-style ping); turn off for high-volume agents (the inbox will explode otherwise).

## Deleting leads

Delete a single lead from its row, or bulk-delete filtered leads from the toolbar. Deleting a lead leaves the underlying conversation intact — the lead disappears, the chat doesn't.

## Leads on protected agents

If the agent uses [identity verification](/guide/account/identity), the chatter is *already* known — the lead form usually isn't necessary. Your team can still enable it to collect richer attributes (phone, company), or skip it entirely and use the JWT's `sub` claim plus your own customer database.

## Leads via API

The conversations API exposes the attached lead object. See the [API reference](https://chatvia.ai/docs/api) under **Conversations**.
