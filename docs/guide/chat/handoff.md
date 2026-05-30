# Human handoff

Sometimes the AI isn't enough. **Handoff** lets a visitor request a real person, and lets your team take over the conversation live.

## How a visitor requests it

In the widget, the visitor can click **Talk to a human** (label and placement configurable in **Widget → Behavior**). The conversation moves into a **pending** state:

- The visitor sees a "waiting for an agent…" message.
- The widget shows a spinner instead of the AI typing indicator.

## How your team accepts

From the dashboard, open the agent's **Handoff → Pending** queue:

![SCREENSHOT — pending handoffs](/screenshots/handoff-pending.png)

You'll see incoming requests. Click **Take over** and you're live with the visitor. The conversation is marked as human-controlled and a real-time channel opens between you and the visitor so messages flow both ways instantly.

You can now type replies, see the visitor's typing indicator, and they see yours. Past messages from the AI stay in the thread for context.

## Returning to the AI

When you're done, click **Hand back to AI** in the conversation panel. The widget switches back to AI mode and the visitor's next message goes through the model again.

The handoff history (who took over, when, for how long) is preserved on the conversation.

## Multi-agent operator

If you operate several agents, the **Pending** queue is per-agent. There's no global queue (yet) — open each agent's pending page or filter by team.

## Notifications

In the agent's **Handoff → Settings**:

- **Email notifications** — send a mail to a list of addresses when a pending request comes in.
- **Webhook** — POST to a URL of your choice so you can wire up Slack, PagerDuty, or whatever else.

## What the visitor sees during handoff

- **Pending** — "Connecting you to an agent…" with a spinner.
- **Connected** — admin replies appear with a different avatar (your dashboard avatar, by default).
- **Returned** — "You're now back with the AI assistant." plus a brief note.

You can customize all three messages in **Widget → Text**.

## When to disable handoff

If you don't have humans available, turn handoff off in **Widget → Behavior**. The "Talk to human" button disappears and the agent simply suggests email or another channel when it can't help.

Keeping handoff on without staffing it is worse than not having it — visitors wait, time out, leave frustrated.
