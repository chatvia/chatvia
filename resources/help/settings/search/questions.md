People often ask short follow-up questions, like "and the price?". On its own, a message like this is hard to search.

- **Understand follow-up questions** — Before the search, an AI step reads the last few messages of the chat and turns the follow-up into a full question, for example "How much does express delivery cost?". The full question is only used for the search; the agent still answers what the person wrote. It uses the **Follow-up questions** model (Settings → AI models → Models for app tasks) and adds one short model call to each follow-up. The first message of a chat needs no call. If the call fails, the message is searched as it was written.

**Tip:** Keep it on. Turn it off only to save model calls.
