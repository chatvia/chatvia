Lets workspaces connect a WhatsApp Business phone number to an agent, so the agent answers WhatsApp messages. They connect it on the agent's Channels page, in a Meta window called Embedded Signup. First, create an app at Meta for developers (**Open console**).

- **App ID** and **App secret** — From your Meta app's settings. The secret also proves that new messages come from Meta. It is stored encrypted and never shown again. Leave it blank to keep it.
- **Webhook verify token** — Any random text. Paste the same text in your Meta app's webhook settings. A webhook is how Meta tells this app about new messages. The token is stored encrypted.
- **Login configuration ID** — The "Facebook Login for Business" setup that the Embedded Signup window opens. Without it and the App ID, workspaces see a message that WhatsApp is not set up.
- **Number registration PIN** — Six digits used to register each connected number with the Cloud API (Meta's service for WhatsApp messages). Do not use 000000. Leave it empty to skip this step.

Copy the **Webhook callback URL** under "Register these URLs with the provider" into your Meta app.
