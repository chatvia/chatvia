# Q&A pairs

Explicit question/answer pairs that take precedence over RAG retrieval. The closest thing Chatvia has to a "scripted reply."

![SCREENSHOT — Q&A source list](/screenshots/sources-qa.png)

## When to use a Q&A pair

- A **canonical question** the agent gets a lot, and you want a deterministic answer.
- A **legal-sensitive** answer where wording matters (privacy, billing, regulated industries).
- A **fallback** for an FAQ topic where the model's free-form answer keeps drifting.

A few pairs go a long way — you don't need to script everything.

## Adding a Q&A pair

In **Sources → Q&A**, click **Add Q&A**:

- **Question** — the visitor-style phrasing. Add a few variations if the same question is asked many ways (one variation per pair, or use the **Variants** field if present).
- **Answer** — the exact response the agent will use.

The question is what gets embedded and matched; the answer is served back verbatim. So:

- Write the **question** the way a visitor would say it.
- Write the **answer** the way you want the agent to reply.

## How retrieval prefers Q&A

When a visitor's question matches a Q&A pair with high similarity, Chatvia uses the pair's answer directly instead of asking the LLM to compose one. The LLM is still in the loop for tone and formatting, but the content is anchored to your answer.

If similarity is low, retrieval falls back to other sources as usual.

## Variants

Adding several phrasings of the same question (one Q&A pair each, same answer) improves match rate. For example:

```
Q: How do I cancel my subscription?
A: You can cancel from Settings → Billing. Your access continues until the end of the current billing period.

Q: Where do I unsubscribe?
A: You can cancel from Settings → Billing. Your access continues until the end of the current billing period.

Q: I want to stop my account
A: You can cancel from Settings → Billing. Your access continues until the end of the current billing period.
```

## Editing

Edits replace the chunk atomically — no downtime.

## Q&A vs. Text vs. Files

Rule of thumb:

- **Files / Web / YouTube** — broad knowledge the model should reason over.
- **Text** — short content the model should reference.
- **Q&A** — specific questions you want answered the same way every time.
