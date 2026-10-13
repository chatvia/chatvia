Pick which AI service does each background job. Each service needs its API key on its card below.

- **Embeddings** — Makes embeddings (number codes that let the agent find text by meaning). The service must make codes of the size this install stores, or saving fails. It is locked once sources are trained.
- **Reranking** — Checks search results and tools again and puts the best first. "Automatic" uses your embeddings service's own reranker, or none. If reranking fails, the agent still answers.
- **Document reading** — Reads PDFs, office files and images. "Automatic" uses Mistral OCR when a Mistral key is saved, else the local tools. Local tools read only text, not scans or images.
- **Speech to text** — For voice input and audio or video files. Choose Scaleway AI or OpenAI; both use a Whisper model. "Automatic" uses Scaleway when a Scaleway key is saved, else OpenAI when an OpenAI key is saved. "Off" turns these off.
- **Whole-PDF reading** — Writes source summaries and answers questions about PDF files in a chat. "Automatic" uses Mistral when a Mistral key is saved. "Off" turns both off.

**Tip:** Not sure? Keep "Automatic".
