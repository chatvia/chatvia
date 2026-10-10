# WhatsApp

Connect a WhatsApp Business number to your agent. People message the number on WhatsApp, and your agent answers — with the same knowledge, guardrails, history, and message limits as every other channel.

WhatsApp is available for **public agents only**. A WhatsApp sender is anonymous to Chatvia, so a protected agent cannot answer there. You can make an agent public on its **Security** page.

## Before you start

You need:

- A **Meta (Facebook) account** with a [business portfolio](https://business.facebook.com/).
- About 15 minutes for the one-time Meta setup.

::: warning Registering a real number
A phone number registered with the WhatsApp Business Platform **can no longer be used in the WhatsApp or WhatsApp Business phone apps**. Meta gives you a free test number first — use it until you are ready to commit a real number.
:::

## Step 1 — Create a Meta app

Go to [developers.facebook.com/apps](https://developers.facebook.com/apps) and click **Create App**. Give the app a name and a contact email:

![SCREENSHOT — create an app, app details step](/screenshots/whatsapp-create-app.png)

On the **Use cases** step, select **Connect with customers through WhatsApp**:

![SCREENSHOT — use case selection with WhatsApp](/screenshots/whatsapp-use-case-select.png)

On the next steps, pick your business portfolio and finish the wizard.

## Step 2 — Complete the WhatsApp basic setup

In your new app, open **Use cases** in the left sidebar. You will see the WhatsApp use case — click **Customize**:

![SCREENSHOT — use cases page](/screenshots/whatsapp-use-cases.png)

Open **Basic setup → Step 1: Try it out**. Select your business portfolio and click **Continue**:

![SCREENSHOT — WhatsApp Business Platform onboarding](/screenshots/whatsapp-onboarding.png)

Meta creates a **test phone number** for you. It can message up to 5 phone numbers that you verify — enough to try the channel end to end.

## Step 3 — Copy your IDs and access token

After the basic setup, the **Try it out** page shows the API credentials:

- **Phone number ID** — the numeric ID of your WhatsApp number (not the number itself).
- **WhatsApp Business Account ID** — shown right next to it.
- **Access token** — click **Generate** to create one.

::: tip Use a permanent token
The token from this page expires after **24 hours**. It is fine for testing. For production, create a **System User** in your [Business settings](https://business.facebook.com/settings) and generate a permanent token with the `whatsapp_business_messaging` and `whatsapp_business_management` permissions.
:::

## Step 4 — Copy the app secret

Open **App settings → Basic** in the left sidebar. Click **Show** next to **App secret** and copy it. Chatvia uses it to verify that incoming messages really come from Meta.

![SCREENSHOT — app settings basic with app secret](/screenshots/whatsapp-app-secret.png)

## Step 5 — Connect in Chatvia

In Chatvia, open your agent's **Channels** page and click the **WhatsApp** card. Paste all four values:

| Field | Where to find it |
| --- | --- |
| Phone number ID | Use cases → Customize → Step 1: Try it out |
| WhatsApp Business Account ID | Same page, next to the phone number ID |
| Access token | Same page (or your System User token) |
| App secret | App settings → Basic |

Click **Connect**. Chatvia checks the credentials with Meta and subscribes your app to the WhatsApp account automatically.

## Step 6 — Point the webhook at Chatvia

After connecting, the WhatsApp card shows a **Callback URL** and a **Verify token**. In your Meta app, open the WhatsApp use case's **webhook settings** (Use cases → Customize, or WhatsApp → Configuration), click **Edit**, and paste both values. Then subscribe the webhook to the **messages** field.

Meta verifies the URL instantly. If it shows an error, check that you pasted the full Callback URL with no extra spaces.

## Step 7 — Test it

Send a WhatsApp message to your number (for a test number: from one of the verified phone numbers). Your agent marks the message as read, shows a typing indicator while it thinks, and replies. The conversation appears in your agent's **Chat log** with a WhatsApp badge.

## Troubleshooting

| Problem | Likely cause |
| --- | --- |
| Webhook verification fails | Wrong verify token, or the Callback URL was not copied fully. |
| No reply to messages | The webhook is not subscribed to the **messages** field, or the access token expired (24-hour test token). |
| Replies stopped after a day | You used the temporary token. Switch to a permanent System User token and reconnect. |
| Test number can't reach a phone | The recipient is not in the test number's verified recipient list (maximum 5). |
| Agent answers "not available" | The agent is private. Make it public on the Security page. |

## How replies behave

- Replies are sent as one message (or several, if longer than WhatsApp's 4,096-character limit).
- The agent's formatting is converted to WhatsApp's own style: **bold**, _italic_, lists, and quotes all render natively. Links are sent as plain URLs, which WhatsApp makes tappable.
- Chatvia only replies to incoming messages, so replies always fall inside WhatsApp's free 24-hour customer-service window — message costs are effectively zero.
