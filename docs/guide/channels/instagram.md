# Instagram

Connect an Instagram professional account to your agent. People send your account a DM, and your agent answers — with the same knowledge, guardrails, history, and message limits as every other channel. No Facebook Page is needed: Chatvia uses the Instagram API with Instagram login.

Instagram is available for **public agents only**. An Instagram sender is anonymous to Chatvia, so a protected agent cannot answer there. You can make an agent public on its **Security** page.

## Before you start

You need:

- An Instagram **professional account** (business or creator). You can switch a personal account in the Instagram app under **Settings → Account type and tools**.
- A **Meta developer account** ([developers.facebook.com](https://developers.facebook.com)).
- About 10 minutes for the one-time setup.

## Step 1 — Create a Meta app with the Instagram product

Go to [developers.facebook.com/apps](https://developers.facebook.com/apps) and click **Create App**. On the use-case step, pick the **Instagram** option (the app type for the Instagram API with Instagram login), and finish the wizard.

## Step 2 — Generate an access token

In your new app, open **Instagram → API setup with Instagram login** in the left sidebar.

- Under **Generate access tokens**, add your Instagram professional account and log in when asked.
- Click **Generate token** next to the account and copy it. The token is long-lived (about 60 days).

## Step 3 — Copy the app secret

On the same **API setup with Instagram login** page, copy the **Instagram app secret**. Chatvia uses it to verify that incoming messages really come from Meta.

## Step 4 — Connect in Chatvia

In Chatvia, open your agent's **Channels** page and click the **Instagram** card. Paste both values:

| Field | Where to find it |
| --- | --- |
| Access token | Instagram → API setup with Instagram login → Generate access tokens |
| App secret | Same page — Instagram app secret |

Click **Connect**. Chatvia checks the token with Meta and subscribes your app to the account's messages automatically.

## Step 5 — Point the webhook at Chatvia

After connecting, the Instagram card shows a **Callback URL** and a **Verify token**. In your Meta app, on the **API setup with Instagram login** page, open **Configure webhooks**, paste both values, and save. Then make sure the webhook is subscribed to the **messages** field.

Meta verifies the URL instantly. If it shows an error, check that you pasted the full Callback URL with no extra spaces.

## Step 6 — Allow message access in the Instagram app

On the phone, in the Instagram app for your professional account, go to **Settings → Messages and story replies → Message controls → Connected tools** and turn on **Allow access to messages**. Without this switch, Meta does not deliver DMs to any tool.

## Step 7 — Test it

Send a DM to your account from another Instagram account. Your agent shows a typing indicator while it thinks and replies. The conversation appears in your agent's **Chat log** with an Instagram badge.

::: tip App mode and testers
While your Meta app is in **Development mode**, only accounts added as testers (or accounts you own) can message the bot. Switch the app to **Live mode** (this needs Meta's app review for `instagram_business_manage_messages`) to answer everyone.
:::

## Troubleshooting

| Problem | Likely cause |
| --- | --- |
| Webhook verification fails | Wrong verify token, or the Callback URL was not copied fully. |
| No reply to DMs | The webhook is not subscribed to the **messages** field, or message access is off in the Instagram app (Step 6). |
| Replies stopped after ~60 days | The access token expired. Generate a new one and reconnect. |
| Strangers get no reply | The app is still in Development mode — only testers are delivered. |
| Agent answers "not available" | The agent is private. Make it public on the Security page. |

## How replies behave

- Replies are sent as one message (or several, if longer than Instagram's 1,000-character limit).
- Instagram shows no text formatting, so the agent's Markdown is stripped to readable plain text: lists keep their bullets and numbers, and links are sent as plain URLs, which Instagram makes tappable.
- Chatvia only replies to incoming messages, so replies always fall inside Instagram's 24-hour response window.
