# Chat logs

Every conversation a chatter has with an agent is stored and searchable. This is your team's window into what customers are asking and how your agents are answering. Open an agent and click **Chat logs** on the sidebar.

![SCREENSHOT — chat logs](/screenshots/chat-logs.png)

## What your team will see

A paginated list of conversations, newest first. Each row shows:

- The chatter (name and email if collected via [leads](/guide/chat/leads), otherwise IP / city).
- First user message (preview).
- Timestamp.
- Topic, sentiment, and tone tags (if [analytics](/guide/chat/analytics) is enabled).
- Handoff status — `AI`, `pending`, `human`, or `returned`.
- Message count.

Click a row to open the full conversation.

## Filtering

The toolbar lets your team filter by:

- **Date range.**
- **Topic, sentiment, tone.**
- **Country** of the chatter (geo-IP).
- **Handoff status.**
- **Read / unread.**

Combine filters to slice the data — "negative sentiment, last 7 days, US" gives your team a focused review queue.

## Conversation detail

The detail page shows:

- The full message stream — chatter, assistant, and teammate replies (handoff).
- Each assistant message expands to show **retrieved sources** with similarity scores, the **model** used, and the **tokens** consumed.
- A **revise answer** action — re-run the question with a different model or persona without affecting the original message. The revision lands as a new message visible only to your team.

## Marking as read

The list shows unread conversations in **bold**. Opening one (or selecting and clicking **Mark as read**) clears the badge. Useful when your team is triaging the queue.

## Exporting

Click **Export** on the toolbar to download the filtered list as a CSV or JSON. Useful for:

- Quarterly review meetings.
- Bulk re-analysis with your own scripts.
- Compliance audits.

## Deletion and retention

By default conversations are kept indefinitely. Your workspace can:

- **Delete individual conversations** from the detail view.
- **Set a retention window** in Settings — automatic deletion after N days.
- **Bulk delete** filtered conversations from the toolbar.

Deletion is hard — there's no soft-delete. Conversations and their messages are gone immediately.

## Chat logs and chatter privacy

Conversations contain whatever the chatter typed, plus inferred attributes:

- IP address (and derived country / city / region).
- Browser, OS, device.
- Referring page URL.

Chatvia is EU-hosted and GDPR-friendly. If your business is GDPR-sensitive, the **retention window** is the simplest knob. For more aggressive minimization, disable lead capture and use a short retention. The Chatvia DPA covers the storage details.

## Per-conversation feedback

If your agent shows the thumbs-up / thumbs-down icons (a setting in **Behavior**), chatter feedback per message lands in Chat logs as a small icon beside that message. Your team can filter the log by "down-voted" to find conversations worth reviewing.

## Chat logs via API

Your team can pull conversations programmatically for archival, analytics export, or your own internal admin UI. Authenticate with a workspace bearer token. See the [API reference](https://chatvia.ai/docs/api) under **Conversations**.
