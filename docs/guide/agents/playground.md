# Playground

The Playground is where your team tests an agent before chatters reach it. Find it on every agent's sidebar.

![SCREENSHOT — playground](/screenshots/agent-playground.png)

## What your team can do here

- **Chat with the agent** exactly as a chatter would.
- **Preview each surface** — switch the preview tab between **Hosted Chat**, **Widget**, and **Bar** to see how the agent renders on each one. The shared Setup (persona, model, starters, branding) applies across all three.
- **See retrieved sources** — every assistant message shows the chunks the agent pulled from your sources, with similarity scores.
- **Try different models** — switch the model on the fly to compare answers.
- **Tweak the persona** in a side panel without leaving the page.
- **Reset the conversation** to start over from the system prompt.

## Why this is useful

The biggest debugging question with a RAG agent is "why did it say *that*?" The Playground answers it: your team sees the system instructions, the retrieved chunks, and the final response in one place. If the chunks look wrong, your sources need work. If the chunks look right but the response is off, your persona or model needs work.

The surface preview also catches surface-specific issues early — branding that works in Hosted Chat but feels cramped in the Widget, or starters that read well in the Bar modal but not in a floating bubble.

::: tip Reproduce a real conversation
Your team can paste a chatter's exact wording from **Chat logs** into the Playground and replay the question against a different model or a tweaked persona — without losing the original conversation.
:::

## Conversation history

Playground conversations are saved separately from production traffic — they don't show up in Chat logs, analytics, or leads. That makes the Playground safe to use for messy testing.

## Visibility

Only teammates with access to the agent can open the Playground. Chatters (signed in via [identity verification](/guide/account/identity)) cannot — it's strictly a dashboard tool for the workspace.
