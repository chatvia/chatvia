# Model & generation

Every agent in your workspace runs against one of the LLMs your plan provides. The **Model** tab lets your team pick which model and tune its generation parameters. The same model choice applies across all three surfaces of the agent — Hosted Chat, Widget, and Bar.

![SCREENSHOT — model selector](/screenshots/agent-model-selector.png)

## Picking a model

Chatvia ships with a curated catalog of open-weight and proprietary models. The exact list depends on your plan and the providers Chatvia has enabled in your region. You'll typically see:

- **Open-weight models** (Qwen, Llama, Mistral, gpt-oss) — privacy-first; some are hosted in the EU.
- **Frontier models** (Claude, GPT, Gemini) — best quality on hard reasoning tasks.

Recommended approach:

1. Start with the default model.
2. If responses are too generic or miss nuance, upgrade to a larger model.
3. If responses are too slow or too expensive, downshift.

## Generation parameters

You can override per agent:

| Parameter | What it does |
| --- | --- |
| **Temperature** | Higher = more creative, lower = more deterministic. 0.2–0.5 is good for support; 0.7+ for ideation. |
| **Top-p** | Nucleus sampling cutoff. Leave at 1.0 unless you know you need it. |
| **Max tokens** | Cap on response length. Useful for chip-style answers in the Widget or Bar surfaces. |
| **Reasoning effort** *(reasoning models)* | Lets reasoning-capable models think for longer before responding. |

Leaving a field blank uses the model's default.

## Fallback model

If the primary model is unavailable (provider outage, rate limit), Chatvia can fall back to a second model so your customers don't see errors. Configure the fallback in the same panel.

## Models attached vs. allowed

Any agent can expose a **model picker** to chatters (off by default). The list of pickable models is the **Models** array on the agent — multi-select in the panel. The chatter's choice is constrained to that list, and the picker appears on whichever surface they're using (Hosted Chat, Widget, or Bar).

If the picker is off, only the **primary** model is used and the multi-select doesn't apply.

## Model cost and credits

Each message consumes workspace credits based on input + output tokens and the model's price tier. See [Usage & billing](/guide/account/billing) for credit pricing and how to monitor consumption across your team.

::: tip Match the model to the task
Reasoning models are better at multi-step questions ("Given X and Y, what's Z?"). Smaller chat models are faster and cheaper for FAQ-style lookups. If your sources are exhaustive Q&A pairs, a cheap model often does fine.
:::
