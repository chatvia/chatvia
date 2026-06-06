# Human handoff

Sometimes the AI isn't enough. **Handoff** lets a chatter request a real person, and lets a teammate take over the conversation live.

## How a chatter requests it

From any surface (Hosted Chat, Widget, or Bar), the chatter can click **Talk to a human** (label and placement configurable in **Setup → Behavior**). The conversation moves into a **pending** state:

- The chatter sees a "waiting for a teammate…" message.
- The chat shows a spinner instead of the AI typing indicator.

## How a teammate accepts

From the dashboard, open the agent's **Handoff → Pending** queue:

![SCREENSHOT — pending handoffs](/screenshots/handoff-pending.png)

Incoming requests appear here. Any teammate with the handoff capability can click **Take over** to go live with the chatter. The conversation is marked as human-controlled and a real-time channel opens between the teammate and the chatter so messages flow both ways instantly.

The teammate can now type replies, see the chatter's typing indicator, and the chatter sees theirs. Past messages from the AI stay in the thread for context.

## Returning to the AI

When the teammate is done, they click **Hand back to AI** in the conversation panel. The chat switches back to AI mode and the chatter's next message goes through the model again.

The handoff history (which teammate took over, when, for how long) is preserved on the conversation.

## Multi-agent workspaces

If the workspace runs several agents, the **Pending** queue is per-agent. There's no global queue (yet) — open each agent's pending page or filter by teammate.

## Notifications

In the agent's **Handoff → Settings**:

- **Email notifications** — send a mail to a list of addresses when a pending request comes in.
- **Webhook** — POST to a URL of your choice so the team can wire up Slack, PagerDuty, or anything else.

## What the chatter sees during handoff

- **Pending** — "Connecting you to a teammate…" with a spinner.
- **Connected** — teammate replies appear with a different avatar (the teammate's dashboard avatar, by default).
- **Returned** — "You're now back with the AI assistant." plus a brief note.

All three messages are customizable in **Setup → Text**.

## When to disable handoff

If the workspace doesn't have teammates available, turn handoff off in **Setup → Behavior**. The "Talk to human" button disappears and the agent simply suggests email or another channel when it can't help.

Keeping handoff on without staffing it is worse than not having it — chatters wait, time out, and leave frustrated.
