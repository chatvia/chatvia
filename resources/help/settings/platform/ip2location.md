IP2Location is a service that finds the country of a visitor from their IP address (the number of their internet connection). Get an API key in your IP2Location account. The "Open console" link takes you there.

- **API key** — Your key from IP2Location. It is stored encrypted and never shown again. Leave the field blank to keep the saved key. The trash button removes it.
- **Address** (under **Advanced**) — Where the app sends its questions. Change it only if IP2Location gives you a different address.

When the key is empty, no lookup happens. The app then knows a visitor's country only when Cloudflare sends it, and details such as city and time zone stay empty. A workspace can allow or block countries for its dashboard. When the country is unknown, this rule lets the person in.

**Tip:** Add a key if workspaces use country rules or want to see where their visitors are.
