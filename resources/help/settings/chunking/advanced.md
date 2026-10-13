Fine control over how text is split. The defaults suit most agents.

- **Markdown**, **Text**, **Transcript** — The way of splitting for each kind of content. Only the ways that suit a kind are listed:
  - *One section per heading* — one chunk for each heading, small sections together. Long sections are split between paragraphs, lists, code blocks and tables, and every chunk starts with the headings above it (Markdown only).
  - *Paragraphs, then sentences* — splits at natural breaks until every chunk fits the chunk size.
  - *Whole sentences* — fills each chunk with whole sentences (not for Markdown).
  - *Fixed size* — cuts equal pieces, whatever the text looks like (not for Markdown).
- **Tokenizer** — The rule that counts tokens (small pieces of words): the chunk size when it is in tokens, and the size of every stored chunk. cl100k_base suits most models.

**Tip:** Not sure? Keep the defaults, and use "Try it" to compare.
