# Microsoft Teams

Connect an Azure bot to your agent. People chat with the bot in Microsoft Teams — in a personal chat, a group chat, or a channel — and your agent answers, with the same knowledge, guardrails, history, and billing as every other channel. The reply streams in live: the bot posts a message right away and keeps updating it as the answer is generated.

Microsoft Teams is available for **public agents only**. A Teams sender is anonymous to Chatvia, so a protected agent cannot answer there. You can make an agent public on its **Security** page.

## Before you start

You need:

- A **Microsoft Azure account** with permission to create resources ([portal.azure.com](https://portal.azure.com)).
- About 10 minutes for the one-time Azure setup.

The **Azure Bot** resource itself is free on the Standard channels (Teams is one of them). You do not need any other Azure service.

## Step 1 — Create an Azure Bot

In the [Azure portal](https://portal.azure.com), search for **Azure Bot** in the Marketplace and click **Create**.

- Give the bot a handle and pick (or create) a resource group.
- For **Pricing tier**, the free tier is fine.
- For **Type of App**, pick **Multi Tenant** (simplest) or **Single Tenant**.
- Leave **Creation type** on **Create new Microsoft App ID**.

Click **Review + create** and wait for the deployment to finish.

## Step 2 — Copy the App ID and create a client secret

Open the new bot resource and go to its **Configuration** page.

- Copy the **Microsoft App ID** (a GUID).
- Click **Manage Password** next to it. This opens the app registration's **Certificates & secrets** page. Click **New client secret** and copy the secret **Value** right away — Azure shows it only once.
- If you picked **Single Tenant**, also copy the **Directory (tenant) ID** from the app registration's **Overview** page.

## Step 3 — Connect in Chatvia

In Chatvia, open your agent's **Channels** page and click the **Microsoft Teams** card. Paste the values:

| Field | Where to find it |
| --- | --- |
| Microsoft App ID | Azure Bot → Configuration |
| Client secret | App registration → Certificates & secrets (the **Value**, not the ID) |
| Tenant ID | Only for Single Tenant bots — app registration → Overview |

Click **Connect**. Chatvia checks the credentials with Microsoft.

## Step 4 — Point the bot at Chatvia

After connecting, the Microsoft Teams card shows a **Messaging endpoint**. Copy it, go back to your Azure Bot's **Configuration** page, paste it into **Messaging endpoint**, and click **Apply**.

## Step 5 — Add the Teams channel

In your Azure Bot, open **Channels**, pick **Microsoft Teams**, accept the terms, and click **Apply**. This is what lets Teams talk to the bot.

## Step 6 — Test it

Click **Open a chat with your bot in Teams** on the Chatvia card (or open `https://teams.microsoft.com/l/chat/0/0?users=28:<your-app-id>` in Teams). Send a message — the bot posts a reply immediately and the text streams in as the agent thinks. The conversation appears in your agent's **Chat log** with a Teams badge.

To use the bot in a channel or group chat, add it to the team and @mention it by its name.

## Troubleshooting

| Problem | Likely cause |
| --- | --- |
| Connect fails in Chatvia | Wrong App ID or secret, or you pasted the secret's **ID** instead of its **Value**. For Single Tenant bots: the tenant ID is missing or wrong. |
| No reply in Teams | The messaging endpoint was not saved in Azure, or the Microsoft Teams channel was not added under **Channels**. |
| Replies stopped after months | The client secret expired (Azure secrets have an end date). Create a new secret and reconnect in Chatvia. |
| Bot ignores channel messages | In channels the bot only answers when it is @mentioned. |
| Agent answers "not available" | The agent is private. Make it public on the Security page. |

## How replies behave

- The reply streams: the bot posts a placeholder right away and edits it in place as the answer is generated.
- The agent's formatting renders natively in Teams: **bold**, _italic_, lists, links, and code blocks. Headings are shown as bold lines.
- Each personal chat, group chat, or channel thread keeps its own ongoing conversation, so the agent remembers earlier messages in that thread.
