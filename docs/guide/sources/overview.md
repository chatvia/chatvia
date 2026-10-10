# Sources overview

Sources are the knowledge an agent answers from — your business's docs, policies, runbooks, FAQs, and recorded know-how. Without sources an agent will refuse most questions; its persona instructions tell it not to make things up.

Sources are scoped to one specific agent. There is no global "Knowledge Base" page in the dashboard — that view was removed when chat was unified at `/chat`. To manage sources, open the agent and go to its **Sources** tab.

![SCREENSHOT — sources index](/screenshots/sources-overview.png)

## The five types

| Type | Best for | Page |
| --- | --- | --- |
| **Files** | PDFs, Word docs, Markdown, audio, video | [Files](/guide/sources/files) |
| **Web** | Public websites, doc portals | [Web pages](/guide/sources/web) |
| **YouTube** | Tutorials, recorded talks | [YouTube](/guide/sources/youtube) |
| **Text** | Short snippets, policies | [Text snippets](/guide/sources/text) |
| **Q&A** | Direct overrides, canonical answers | [Q&A pairs](/guide/sources/qa) |

## How ingestion works

Adding a source kicks off a background job:

1. **Parse** — extract the text from the source.
2. **Chunk** — split into short, overlapping blocks.
3. **Embed** — turn each block into a vector that captures its meaning.
4. **Index** — store the blocks and their vectors so they can be searched.
5. **Classify** — detect language, summary, and document type for analytics.

You'll see the source's **status** change live: `pending → processing → ingested` (or `failed`). Most ingestions complete in seconds to minutes; very large files can take longer.

## Retrieval at query time

When a chatter asks the agent a question:

1. The question is turned into a vector the same way the sources were.
2. Chatvia finds the closest matching blocks across all of the agent's sources.
3. A second pass reorders the candidates so the most relevant ones come first.
4. Those top blocks are passed to the AI alongside the question and the persona.
5. The AI grounds its answer in those blocks. The Playground and Chat logs show your team which were used.

## Sources and limits

- Adding, processing, and retraining sources does not count against a [monthly message limit](/guide/account/message-limits). Only agent replies count.
- Deleting a source frees its storage immediately and removes its chunks from the index.

## Retraining a source

If the underlying content changes (a web page is updated, your team edits a Markdown file), open the source and click **Retrain**. The old chunks are deleted and re-ingestion runs from scratch. The agent keeps working in the meantime — only the retrained source is briefly unavailable.

## Permissions

Sources belong to the agent, and the agent belongs to your workspace. Any teammate with **write** access on the agent can add or remove sources. Chatters only see the agent's answers in chat — they never see the source list or manage sources themselves.
