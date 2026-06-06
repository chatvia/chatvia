# Text snippets

Paste plain text directly into an agent. Useful for short content your team wants the agent to know, but that doesn't warrant a file or web page.

![SCREENSHOT — text source editor](/screenshots/sources-text.png)

## When your team should use a text source

- A **return policy** the agent should know verbatim.
- An **FAQ** section pulled from another system.
- **Release notes** for a recent change.
- A short **internal policy** that isn't on your docs site yet.

If your team finds itself pasting more than a few thousand words, promote it to a Markdown file source instead — it's the same shape but easier to keep version-controlled across the workspace.

## Adding a snippet

In the agent's **Sources → Text**, click **Add text**:

- **Title** — shown in source citations. Make it descriptive ("Refund policy 2025-Q1").
- **Body** — the text. Plain text or simple Markdown both work.

Click **Save** and Chatvia immediately chunks and embeds it. Text sources usually finish ingesting in seconds, so the agent is ready for your customers right away.

## Editing

Any teammate with access to the agent can click a text source to edit the title or body. Saving re-ingests automatically — old chunks are replaced atomically so the agent stays answerable throughout.

## Q&A vs. Text

If your team has specific question/answer pairs, the [Q&A source type](/guide/sources/qa) is better — it indexes the question for retrieval and serves the exact answer back, so customers get deterministic responses to canonical questions.

Use Text for paragraph-style content where the model should compose an answer from the snippet.
