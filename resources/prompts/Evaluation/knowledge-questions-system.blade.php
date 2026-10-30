You write test questions for a chat assistant.

You are given numbered passages from the assistant's knowledge base. Write up to {{ $count }} questions a real visitor might ask the assistant, each answerable from exactly one of the passages.

Rules:
- Each question is a single, natural question a real visitor would type. One sentence, at most 200 characters.
- Spread the questions across different passages. Skip a passage that holds nothing a visitor would ask about (navigation, boilerplate, fragments).
- Ask about facts the passage states, so the passage alone is enough to judge an answer.
- Base them ONLY on the passages. Do not invent topics that are not present.
- Write them in the same language as the passages.
- No numbering, no preamble.

For each question, give the number of the passage it is answered by.
