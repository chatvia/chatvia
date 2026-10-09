# AI providers

Chatvia uses AI services for several steps: answering in chat, reading documents, splitting text, searching, and turning speech into text. When you run Chatvia on your own server, you choose a service for each step in your `.env` file.

You can also enter the API keys in the admin panel, under **Settings → AI providers**. A key saved there is stored encrypted and is used instead of the key in `.env`. The choice of service for each step stays in `.env`.

You do not need an account with every service. Most steps have a built-in option that runs on your server, or you can turn the step off. With the value `auto`, Chatvia uses the hosted service when its API key is set, and the built-in option (or "off") when it is not.

## Overview

| Step | Setting | Options | What `auto` picks |
| --- | --- | --- | --- |
| Chat answers | `LLM_PROVIDER` | `scaleway`, `mistral`, `openrouter`, `groq` | — (default `scaleway`) |
| Search vectors (embeddings) | `AI_EMBEDDING_PROVIDER` | `qwen`, `cohere`, `jina`, `mistral` | — (default `qwen`) |
| Ranking search results | `AI_RERANKER` | `qwen`, `cohere`, `none` | The reranker of your embedding provider (`qwen` or `cohere`), else `none` |
| Reading documents | `AI_DOCUMENT_PARSER` | `mistral`, `local` | `mistral` when `MISTRAL_API_KEY` is set, else `local` |
| Splitting text into chunks | `AI_TEXT_SPLITTER` | `chunkfit`, `local` | `chunkfit` when `CHUNK_FIT_API_KEY` is set, else `local` |
| Speech to text | `AI_TRANSCRIBER` | `scaleway`, `none` | `scaleway` when `SCALEWAYAI_API_KEY` is set, else `none` |
| Reading whole PDF files | `AI_DOCUMENT_LLM` | `mistral`, `none` | `mistral` when `MISTRAL_API_KEY` is set, else `none` |

If `AI_RERANKER`, `AI_DOCUMENT_PARSER`, `AI_TEXT_SPLITTER`, `AI_TRANSCRIBER` or `AI_DOCUMENT_LLM` has a value that Chatvia does not know, Chatvia stops with an error that names the setting and the allowed values.

## The smallest setup

You need two things:

1. A chat provider (`LLM_PROVIDER` and its API key).
2. An embedding provider (`AI_EMBEDDING_PROVIDER` and its API key).

Everything else can run on your server or stay off.

We suggest open-weight models. The default `scaleway` and `qwen` options use open-weight Qwen3 models for chat, search vectors and ranking, and Whisper for speech to text. With one Scaleway API key you get all of these:

```dotenv
LLM_PROVIDER=scaleway
SCALEWAYAI_API_KEY=your-key
AI_EMBEDDING_PROVIDER=qwen
AI_RERANKER=auto
AI_DOCUMENT_PARSER=local
AI_TEXT_SPLITTER=local
AI_TRANSCRIBER=auto
AI_DOCUMENT_LLM=none
```

The `scaleway` and `qwen` options use the common OpenAI-style API (`/chat/completions`, `/embeddings`, `/rerank`, `/audio/transcriptions`). You can set `SCALEWAYAI_API_ENDPOINT` to another server that offers the same API, for example a server where you run open-weight models yourself.

## Chat answers

`LLM_PROVIDER` sets where chat requests go. Each provider needs its own API key:

| Provider | API key | Address |
| --- | --- | --- |
| `scaleway` | `SCALEWAYAI_API_KEY` | `SCALEWAYAI_API_ENDPOINT` |
| `mistral` | `MISTRAL_API_KEY` | `MISTRAL_BASE_URL` |
| `openrouter` | `OPENROUTER_API_KEY` | `OPENROUTER_BASE_URL`, `OPENROUTER_COMPLETION_ENDPOINT` |
| `groq` | `GROQ_API_KEY` | `GROQ_BASE_URL`, `GROQ_COMPLETION_ENDPOINT` |

The models your agents can pick come from the model list in the database. Each model in that list must exist at the provider you choose.

## Search vectors (embeddings)

Chatvia turns every chunk of your content into a vector, so it can find the chunks that match a question.

| Provider | Settings |
| --- | --- |
| `qwen` | `SCALEWAYAI_API_KEY`, `SCALEWAYAI_EMBEDDING_MODEL` (default `qwen3-embedding-8b`) |
| `cohere` | `COHERE_API_KEY`, `COHERE_EMBEDDING_MODEL`, `COHERE_EMBEDDING_DIMENSIONS` |
| `jina` | `JINA_API_KEY`, `JINA_EMBEDDING_MODEL`, `JINA_EMBEDDING_DIMENSIONS` |
| `mistral` | `MISTRAL_API_KEY`, `MISTRAL_EMBEDDING_MODEL` (default `mistral-embed`) |

::: warning Vector size
The database stores vectors with **1024 dimensions**. The embedding model must return exactly 1024 dimensions. If it does not, Chatvia stops with a clear error the first time it uses the provider, before anything is saved.
:::

::: warning Changing the provider
Vectors from different models do not match. If you change the embedding provider or model after you have added content, train your sources again.
:::

## Ranking search results

After the first search, a reranker scores each result against the question. Results with a low score are dropped. The same scores decide which tools an agent is offered for a message.

| Option | Settings |
| --- | --- |
| `qwen` | `SCALEWAYAI_API_KEY`, `SCALEWAYAI_RERANK_MODEL` |
| `cohere` | `COHERE_API_KEY`, `COHERE_RERANK_MODEL` (default `rerank-v4.0-pro`) |
| `none` | No ranking. Search keeps the database order, and agents are offered every tool the first search finds. |

## Reading documents

When you upload a PDF, office file or image as a source, Chatvia reads its text page by page.

- **`mistral`** uses a hosted OCR service. It reads PDFs, Word, PowerPoint and Excel files, images and scanned pages. Settings: `MISTRAL_API_KEY`, `MISTRAL_OCR_MODEL`.
- **`local`** runs on your server. PDFs are read with `pdftotext` (from the poppler tools). Office files are first turned into a PDF with LibreOffice. It reads only the text in a file: it cannot read images or scanned pages. An image upload then fails with a short message that explains this.

For `local`, install poppler and LibreOffice. If they are not on the `PATH` of your queue worker, set `PDFTOTEXT_BIN_PATH` and `LIBREOFFICE_BIN_PATH`.

Text files, Markdown and ebooks (EPUB, FB2) do not use this step. Chatvia reads them directly.

## Splitting text into chunks

Text files, ebooks and web pages are split into chunks before they are saved.

- **`chunkfit`** uses the hosted chunk.fit service. It splits by section title. Settings: `CHUNK_FIT_API_KEY`, `CHUNK_FIT_BASE_URL`.
- **`local`** is built in and needs no extra software. It keeps whole paragraphs together, in chunks of about 400 tokens. You can change the size in `config/chatvia.php` under `ai.local_splitter`.

## Speech to text

Speech to text is used for voice input in chat and for audio and video sources.

- **`scaleway`** uses a Whisper model. Settings: `SCALEWAYAI_API_KEY`, `SCALEWAYAI_TRANSCRIPTION_MODEL` (default `whisper-large-v3`).
- **`none`** turns the step off. Voice input then answers "Audio transcription is not configured on this server.", and audio and video sources fail with the same message.

Audio is compressed with `ffmpeg` before it is sent. Set `FFMPEG_BIN_PATH` if `ffmpeg` is not on the `PATH`.

## Reading whole PDF files

Two features need a model that can read a whole PDF file at once:

- **Document summaries.** Each new source gets a short summary and a list of the questions it answers. This helps search.
- **Chat with PDF files.** Visitors can attach a PDF in the chat and ask questions about it.

| Option | Settings |
| --- | --- |
| `mistral` | `MISTRAL_API_KEY`, and the models `AI_SUMMARY_MODEL`, `AI_ANALYSIS_MODEL` (default `mistral-large-latest`) and `AI_DOCUMENT_CHAT_MODEL` (default `mistral-medium-latest`) |
| `none` | Both features are off. New sources get no summary, but search still works on their pages. PDF uploads in chat are refused with a short message, and the chat widget hides its upload button. |

The model for chat with PDF files must support both PDF files and tool calls, so agent tools still work in those chats.
