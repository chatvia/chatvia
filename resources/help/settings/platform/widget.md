Where the chat widget may appear, and how much it may be used. The Command Bar uses the same script and limits.

- **Sites every widget may load on** — Websites where every agent's widget may run, on top of each agent's own sites. This install's own address is always allowed. Separate host names with commas, for example `example.com, app.example.com`. `*.example.com` also allows all subdomains. Add a site only if all agents should work there.
- **Widget script address** (under **Advanced**) — Where websites load the widget file from. The default is the copy this server builds. Enter a full address only to use a CDN (a network that delivers files quickly). New embed code uses this address. Sites that already added the code keep the old one.
- **Replies per agent per minute** and **Replies per agent per hour** (under **Advanced**) — The most replies one agent may give in the widget, for all visitors together. When a limit is reached, visitors must wait a little. Every reply uses the AI model, so this stops abuse.

**Tip:** Raise the limits only if a busy agent reaches them.
