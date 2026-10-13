A connector lets workspaces connect {title} to their agents, so the agents can use it. To connect, a person signs in to {title} and allows access. For this, the app needs an OAuth app at {title} (a small app you create there, which asks people for this access).

- **Client ID** and **Client secret** — Create the OAuth app at {title}. When the card has an **Open console** link, it takes you there. Copy the **Redirect URL** under "Register these URLs with the provider" into the OAuth app. It is the address {title} sends people back to. Then paste the Client ID and Client secret here. The secret is stored encrypted and never shown again. Leave it blank to keep the saved one.

While these fields are empty, people who try to connect {title} see a message that the connector is not available.

**Tip:** Every connector uses the same Redirect URL.
