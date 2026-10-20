Agents learn from small parts of your texts, called chunks. Here you set how texts are cut into chunks. The defaults suit most agents.

- **Measure in** — Count the length in tokens (small pieces of words; AI models count text in tokens) or in characters (letters, digits, spaces and signs). Characters are easier to picture.
- **Chunk size** — The largest a chunk may be: 50 to 8,000 tokens, or 200 to 32,000 characters. 300 to 500 tokens (about 1,200 to 2,000 characters) suits most agents. Leave it empty for the default: 400 tokens or 1,600 characters.
- **Overlap** — How much from the end of one chunk is repeated at the start of the next one. It must be smaller than the chunk size. Most text needs none.
- **Markdown**, **Text**, **Transcript** — The way of splitting each kind of content. Markdown is web pages, Markdown files and ebooks; Text is typed texts and .txt files; Transcript is the text of audio and video. Only the ways that suit a kind are listed:
    - _One section per heading_ — one chunk for each heading, small sections together; every chunk starts with the headings above it (Markdown only).
    - _Paragraphs, then sentences_ — splits at natural breaks until every chunk fits the chunk size.
    - _Whole sentences_ — fills each chunk with whole sentences (not for Markdown).
    - _Fixed size_ — cuts equal pieces, whatever the text looks like (not for Markdown).
- **Tokenizer** — The rule that counts tokens: the chunk size when it is in tokens, and the size of every stored chunk. cl100k_base suits most models.

PDF, Word, slides, spreadsheets and images make one chunk per page; a page longer than the chunk size is split as Text. A change applies to sources trained after you save.

**Tip:** Use **Try splitting** to see the result before you save.
