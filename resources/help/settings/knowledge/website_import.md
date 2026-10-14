Firecrawl is an online service that imports websites. It finds the pages of a site and reads each page, so agents can learn from them.

- **Firecrawl API key** — Create it on the Firecrawl website ("Get a key"). It is stored encrypted and never shown again. Leave the field blank to keep the saved key. Without a key, workspaces cannot add websites as sources.

Under **Advanced**:

- **Address** — The Firecrawl server to use. Leave it blank for the default. Change it only if you run your own Firecrawl server.
- **Pages per minute** — The most pages read through Firecrawl in one minute, for the whole platform. Set it to about 80% of the per-minute limit of your Firecrawl plan. When the limit is reached, the next pages wait a few seconds and try again. 0 means no limit.

**Tip:** If website imports fail because of Firecrawl's limit, lower **Pages per minute**.
