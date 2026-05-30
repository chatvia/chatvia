# Web sources

Point Chatvia at a URL and it'll crawl, parse, and index the page — and optionally child pages on the same domain.

![SCREENSHOT — web sources](/screenshots/sources-web.png)

## Adding a URL

In **Sources → Web**, paste the URL and click **Add**.

Chatvia immediately:

1. Fetches the page and extracts the main text (skipping nav, footer, ads).
2. Discovers internal links on the same domain.
3. Crawls those links, depth-limited and rate-limited.
4. Indexes each page as a separate **link** under one parent **website** source.

You'll see a progress card with **discovered**, **processing**, and **ingested** counts.

## Controlling the crawl

Open the website source to see:

- A list of all discovered links with their ingestion status.
- Toggles to **include** or **exclude** specific links from indexing.
- A **Retrain** button to re-crawl from scratch.

Exclude noisy pages (login screens, archive indexes, terms of service) — they pollute retrieval. Include only the pages a visitor's question could plausibly map to.

## Limits and politeness

- Max depth is capped (typically 3 levels deep).
- Max pages per website is capped by plan (typically a few hundred).
- The crawler honors `robots.txt` and uses a Chatvia user agent — so site owners can identify and rate-limit it.
- Pages behind authentication are not crawled. For private content, use a [file source](/guide/sources/files) instead.

## Re-crawling on a schedule

Web sources don't auto-refresh. If the underlying site changes often, you have two options:

- **Manual retrain.** Click **Retrain** on the website source whenever you publish changes.
- **API retrain.** Call the Sources API in your CI/CD pipeline so the agent's knowledge updates on every deploy.

::: tip Pair the API and a webhook
Many docs sites already emit a webhook on publish (e.g. from a CMS). Subscribe to that webhook and call the Chatvia API to retrain only the URLs that changed.
:::

## Single-page apps

For JS-rendered SPAs, Chatvia runs a headless browser so client-side content is captured. If you find a SPA isn't being indexed correctly, file a support ticket with the URL — the crawler may need a per-domain configuration.

## Metadata

Each ingested link has:

- Page title and canonical URL.
- Detected language.
- Last crawled timestamp.
- Retrieval count.

You can filter and search across all links of a website.

## Web sources via API

You can add and refresh URL sources programmatically. See the [API reference](https://chatvia.ai/docs/api) under **Sources**.
