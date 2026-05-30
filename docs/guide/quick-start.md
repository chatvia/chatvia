# Quick start

In five minutes you'll have a working agent that answers questions from your own content.

## 1. Sign up

Create an account at [chatvia.ai](https://chatvia.ai) and start a free trial. A new account comes with a personal team, message credits, and a sample agent.

![SCREENSHOT — sign up / trial page](/screenshots/dashboard.png)

## 2. Create an agent

From **Dashboard → Agents → Create agent**, pick a type:

- **Knowledge base** — a hosted chat page that lives on a subdomain (`{your-agent}.chatvia.ai`).
- **Widget** — an embeddable chat bubble you'll drop on your own site.

Give it a name and (optionally) generate a subdomain. You can change everything later.

![SCREENSHOT — create agent modal](/screenshots/agent-create-modal.png)

## 3. Add sources

Open the new agent and go to **Sources**. Add at least one source — these are what your agent answers from:

- **Files** — PDFs, Word docs, Markdown, audio, video.
- **Web pages** — a URL; Chatvia crawls and indexes child links.
- **YouTube** — a video URL; the transcript is indexed.
- **Text** — paste a snippet (FAQ, policy text, etc.).
- **Q&A** — explicit question/answer pairs that override RAG retrieval.

Sources are processed in the background. Status moves from **Pending → Processing → Ingested** (or **Failed**). You can keep working while ingestion finishes.

![SCREENSHOT — sources overview](/screenshots/sources-overview.png)

## 4. Test it in the Playground

Open **Playground** on the agent's sidebar and ask a question. The playground shows the answer plus the source chunks the model retrieved — handy for debugging "why did it say that?"

![SCREENSHOT — playground](/screenshots/agent-playground.png)

## 5. Put it in front of users

You have three options:

- **Share the hosted URL.** For a Knowledge-base agent, copy the subdomain link from the agent header and share it.
- **Embed the widget.** For a Widget agent, open **Widget → Embed** and copy the `<script>` snippet. Paste it before `</body>` on your site. See the [Widget guide](/embed/widget) for customization.
- **Use the API.** Create a key in [Settings → API keys](/guide/account/api-keys) and call the [public API](https://chatvia.ai/docs/api).

::: tip Next steps
- Tighten the persona in [**Persona & instructions**](/guide/agents/persona).
- Add conversation [**Starters**](/guide/agents/starters) so visitors see useful prompts on open.
- Sign your own users in with [**Identity verification**](/guide/account/identity).
:::
