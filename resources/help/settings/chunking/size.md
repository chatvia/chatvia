Agents learn from small parts of your texts, called chunks. Here you set how long a chunk may be.

- **Measure in** — Count the length in tokens (small pieces of words; AI models count text in tokens) or in characters (letters, digits, spaces and signs). Tokens use the tokenizer under Advanced. Characters are easier to picture.
- **Chunk size** — The largest a chunk may be: 50 to 8,000 tokens, or 200 to 32,000 characters. 300 to 500 tokens (about 1,200 to 2,000 characters) suits most agents. Larger chunks give the agent more text in each search result. Leave it empty for the default: 400 tokens or 1,600 characters.
- **Overlap** — How much from the end of one chunk is repeated at the start of the next one, so text near the border is in both. It must be smaller than the chunk size. Most text needs none.

PDF, Word, slides, spreadsheets and images are not affected: each page is one chunk. A change applies to sources trained after you save. Sources trained before keep their chunks until they are trained again.

**Tip:** Use "Try it" on the next card to see the result before you save.
