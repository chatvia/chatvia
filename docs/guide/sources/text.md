# Text snippets

Paste plain text directly. Useful for short content that doesn't warrant a file or web page.

![SCREENSHOT — text source editor](/screenshots/sources-text.png)

## When to use a text source

- A **return policy** you want the agent to know verbatim.
- An **FAQ** section pulled from another system.
- **Release notes** for a recent change.
- A short **policy** that's not in your docs site yet.

If you find yourself pasting more than a few thousand words, consider promoting it to a Markdown file source instead — it's the same shape but easier to keep version-controlled.

## Adding a snippet

In **Sources → Text**, click **Add text**:

- **Title** — shown in source citations. Make it descriptive ("Refund policy 2025-Q1").
- **Body** — the text. Plain text or simple Markdown both work.

Click **Save** and Chatvia immediately chunks and embeds it. Text sources usually finish ingesting in seconds.

## Editing

Click any text source to edit the title or body. Saving re-ingests automatically — old chunks are replaced atomically so the agent stays answerable throughout.

## Q&A vs. Text

If you have specific question/answer pairs, the [Q&A source type](/guide/sources/qa) is better — it indexes the question for retrieval and serves the exact answer back, so you get deterministic responses to canonical questions.

Use Text for paragraph-style content where the model should compose an answer from the snippet.
