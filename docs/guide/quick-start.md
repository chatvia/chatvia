# Quick start

In five minutes your workspace will have a working agent that answers questions from your own content.

## 1. Sign up

Create a workspace at [chatvia.ai](https://chatvia.ai) and start a free trial. A new workspace comes with message credits, a sample agent, and seats for your teammates.

![SCREENSHOT — sign up / trial page](/screenshots/dashboard.png)

## 2. Create an agent

From **Dashboard → Agents → Create agent**, give the agent a name and press **Create**. That is the only field — every agent ships with three surfaces (Hosted Chat, Widget, and Bar) which you configure later in **Setup**. The subdomain is generated for you and can be renamed any time under **Agent → Dashboard → Rename**.



![SCREENSHOT — create agent modal](/screenshots/agent-create-modal.png)

## 3. Add sources

Open the new agent and go to **Sources**. Sources live with the agent — there is no separate Knowledge Base page. Add at least one source so the agent has something to answer from:

- **Files**: PDFs, Word docs, Markdown, audio, video.
- **Web pages**: a URL; Chatvia crawls and indexes child links.
- **YouTube**: a video URL; the transcript is indexed.
- **Text**: paste a snippet (FAQ, policy text, etc.).
- **Q&A**: explicit question/answer pairs that override RAG retrieval.

Sources are processed in the background. Status moves from **Pending → Processing → Ingested** (or **Failed**). Your team can keep working while ingestion finishes.

![SCREENSHOT — sources overview](/screenshots/sources-overview.png)

Chativ.ai is not limited to 

## 4. Test it in the Playground

Open **Playground** on the agent's sidebar and ask a question. The playground previews each of the three surfaces and shows the answer plus the source chunks the model retrieved — handy for debugging "why did it say that?"

![SCREENSHOT — playground](/screenshots/agent-playground.png)

## 5. Put it in front of users

Every agent has three surfaces, plus the unified chat hub and the public API. Pick the channels that match your business:

- **Unified chat at `/chat`.** Invite your team or customers as **chatters** from **Settings → Members** (or from the agent's **Members** tab for a per-agent grant). Signed-in chatters open `/chat` and switch between every agent they have access to — ChatGPT-style.
- **Embed the Widget.** Open **Embed → Widget** on the agent and copy the `<script>` snippet. Paste it before `</body>` on your marketing site or app. See the [Widget guide](/embed/widget).
- **Share the Hosted Chat URL.** Each agent has a standalone chat at `{agent}.chatvia.ai` (or your own domain via CNAME). Good for iframe embeds and SSO scenarios.
- **Drop in the Bar.** A search-style trigger that opens a full-screen chat modal — ideal for in-app assistants. Configure it per agent under **Embed → Bar**.
- **Use the public API.** Create a key in [Settings → API keys](/guide/account/api-keys) and call the [public API](https://chatvia.ai/docs/api) for programmatic access.

::: tip Next steps
- Invite teammates and chatters from [**Settings → Members**](/guide/account/identity).
- Hook up the rest of your stack with [**Tools → Connectors**](/guide/agents/persona) and [**Tools → Functions**](/guide/agents/persona).
- Tighten the persona in [**Persona & instructions**](/guide/agents/persona).
- Add conversation [**Starters**](/guide/agents/starters) so chatters see useful prompts on open.
- Sign your own users in with [**Identity verification**](/guide/account/identity).
:::
