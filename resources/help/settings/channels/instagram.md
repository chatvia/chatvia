Lets workspaces connect an Instagram professional account (a business or creator account linked to a Facebook Page) to an agent, so the agent answers its direct messages. They connect it on the agent's Channels page. First, create an app at Meta for developers (**Open console**).

- **App ID** and **App secret** — From your Meta app's settings. The secret also proves that new messages come from Meta. It is stored encrypted and never shown again. Leave it blank to keep it.
- **Webhook verify token** — Any random text. Paste the same text in your Meta app's webhook settings. A webhook is how Meta tells this app about new messages. The token is stored encrypted.
- **Login configuration ID** (under **Advanced**) — A "Facebook Login for Business" setup in your Meta app. It lists the permissions to ask for. Leave it empty to ask the classic way.
- **Another redirect URL** (under **Advanced**) — Where Meta sends people back after they allow access. Leave it empty to use the URL shown.

Copy both URLs under "Register these URLs with the provider" into your Meta app.

**Tip:** One Meta app can serve Messenger, Instagram and WhatsApp.
