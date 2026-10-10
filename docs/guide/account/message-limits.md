# Message limits

Every workspace can use every feature. To control how much the agents are used, Chatvia has optional **monthly message limits**. There are two kinds:

| Limit | What it counts | Who sets it |
| --- | --- | --- |
| **Workspace limit** | Every reply from every agent in the workspace | The admin of your Chatvia server |
| **Chatter limit** | The messages one chatter sends | Your workspace, in the dashboard |

Without a limit, there is no cap. Both counts start again at the start of each calendar month.

## The workspace limit

The workspace limit counts every agent reply:

- in Hosted Chat, the Chat Widget, and the Command Bar,
- in the Playground,
- on messaging channels (Slack, Microsoft Teams, Telegram, WhatsApp, Messenger, Instagram),
- for each eval case that got an answer.

These are **not** counted:

- A message that a guardrail blocked.
- A reply that failed, so the agent did not answer.
- Messages from a teammate during a human handoff.
- Adding, processing, or retraining sources.

Each reply counts as one message, whatever the model and however long the answer is.

### When the limit is reached

Until the next month starts:

- The chat shows: "This agent has reached its monthly message limit. Please try again next month."
- Messaging channels reply with a short message that the assistant is unavailable right now.
- An eval run does not start. A run that is already going skips its remaining cases.

### See your usage

The **Messages This Month** card on the dashboard shows how many replies your agents gave this month. When your workspace has a limit, it also shows how much of the limit you used.

### Change the workspace limit

Only the admin of your Chatvia server can change it. If you run your own server, see [Workspaces and sign-up](/guide/self-hosting/#workspaces-and-sign-up).

## The chatter limit

A chatter limit caps how many messages one [chatter](/guide/core-concepts#workspaces-teammates-chatters) can send each month, across all agents. It counts on top of the workspace limit: a chatter can send a message only when both limits allow it.

To set it:

1. Open **Workspace → Chatters** and click a chatter.
2. Open the **Limits** tab.
3. Enter a number under **Monthly message limit** and save. Leave the field empty, or click **Remove limit**, for no limit.

The tab also shows how many messages the chatter used this month. If you lower a limit, the messages already used this month still count, so the chatter can be stopped right away.

When a chatter reaches the limit, the chat shows: "You have reached your monthly message limit." The chatter can chat again next month, or when you raise the limit.
