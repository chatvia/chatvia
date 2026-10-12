# AI providers

Chatvia uses AI services for several steps: answering in chat, reading documents, splitting text, searching, and turning speech into text. When you run Chatvia on your own server, you choose a service for each step in the admin panel (`/admin`), under **Settings → AI**.

The API key, address and models of each provider are on the same page. Keys are stored encrypted. Website import is under **Settings → Knowledge**, and how text is split into chunks under **Settings → Chunking**.

You do not need an account with every service. Most steps have a built-in option that runs on your server, or you can turn the step off. With **Automatic**, Chatvia uses the hosted service when its API key is set, and the built-in option (or "off") when it is not.

## Overview

| Step (Settings → AI → Services) | Options | What **Automatic** picks |
| --- | --- | --- |
| Chat | Scaleway AI, Mistral, OpenRouter, Groq | — (default Scaleway AI) |
| Embeddings (search vectors) | Qwen3 on Scaleway AI, Cohere, Jina, Mistral | — (default Qwen3 on Scaleway AI) |
| Reranking | Qwen3 on Scaleway AI, Cohere, Off | The reranker of your embedding provider (Qwen3 or Cohere), else off |
| Document reading | Mistral OCR, Local tools | Mistral OCR when a Mistral API key is set, else local tools |
| Text splitting (Settings → Chunking) | Chunker, Built-in | The chunker when the hosted chunk.fit has its API key or the address points to your own chunker, else the built-in splitter. The Docker stack always uses its own chunker. |
| Speech to text | Scaleway AI (Whisper), Off | Scaleway AI when its API key is set, else off |
| Whole-PDF reading | Mistral, Off | Mistral when a Mistral API key is set, else off |

A field you leave empty uses the default shown in it.

## The smallest setup

You need two things:

1. A chat provider, and its API key.
2. An embedding provider, and its API key.

Everything else can run on your server or stay off.

We suggest open-weight models. The defaults (Scaleway AI for chat, Qwen3 on Scaleway AI for embeddings) use open-weight Qwen3 models for chat, search vectors and ranking, and Whisper for speech to text. With one Scaleway API key you get all of these: open **Settings → AI**, and enter the key under **Scaleway AI**.

Scaleway AI uses the common OpenAI-style API (`/chat/completions`, `/embeddings`, `/rerank`, `/audio/transcriptions`). You can change its **Address** to another server that offers the same API, for example a server where you run open-weight models yourself.

You can also set the values from the command line, for example in a script:

```bash
printf %s "$SCALEWAY_KEY" | php artisan app:setting ai.scaleway_api_key
```

`php artisan app:settings ai` lists every AI setting and its value. Secrets are never shown.

## Chat answers

**Chat** sets where chat requests go. Each provider needs its own API key, entered in its section of **Settings → AI**, where you can also change its address.

The models your agents can pick come from the model list in the database. Each model in that list must exist at the provider you choose. **Model of new agents** (under **Models**) is the model a new agent starts with.

## Search vectors (embeddings)

Chatvia turns every chunk of your content into a vector, so it can find the chunks that match a question. **Embeddings** picks the provider; the model is in the provider's section:

| Provider | Default model |
| --- | --- |
| Qwen3 on Scaleway AI | `qwen3-embedding-8b` |
| Cohere | `embed-v4.0` |
| Jina | `jina-embeddings-v3` |
| Mistral | `mistral-embed` |

::: warning Vector size
The database stores vectors with the size set in `EMBEDDING_DIMENSIONS` when Chatvia was installed (default **1024**). The provider must be able to return that size:

| Size | Providers |
| --- | --- |
| 1024 | All of them |
| 256, 512 or 1536 | Qwen3, Cohere (and Jina for 256 and 512) |
| Any other size up to 4096 | Qwen3 (Jina up to 1024) |

The admin panel refuses a provider that cannot. See [Embedding storage](./index.md#embedding-storage).
:::

::: warning Changing the provider
Vectors from different models do not match. Once a source is trained, the embedding provider and the embedding models are locked in the admin panel. To change them, train your sources again.
:::

## Ranking search results

After the first search, a reranker scores each result against the question. Results with a low score are dropped. The same scores decide which tools an agent is offered for a message.

| Option | Settings |
| --- | --- |
| Qwen3 on Scaleway AI | The Scaleway AI key, and its **Rerank model** |
| Cohere | The Cohere key, and its **Rerank model** (default `rerank-v4.0-pro`) |
| Off | No ranking. Search keeps the database order, and agents are offered every tool the first search finds. |

How strict the ranking is (**Minimum relevance**) and how many results an agent gets are under **Settings → Knowledge → Search**.

## Reading documents

When you upload a PDF, office file or image as a source, Chatvia reads its text page by page.

- **Mistral OCR** uses a hosted OCR service. It reads PDFs, Word, PowerPoint and Excel files, images and scanned pages. It needs the Mistral key; the **OCR model** is in the Mistral section.
- **Local tools** run on your server. PDFs are read with `pdftotext` (from the poppler tools). Word, ODT and RTF files are read with pandoc, old `.doc` and `.ppt` files with catdoc, and PowerPoint and Excel files by Chatvia itself. They read only the text in a file: they cannot read images or scanned pages. An image upload then fails with a short message that explains this.

For local tools, install poppler, pandoc and catdoc. If they are not on the `PATH` of your queue worker, set `PDFTOTEXT_BIN_PATH`, `PANDOC_BIN_PATH`, `CATDOC_BIN_PATH` and `CATPPT_BIN_PATH` in `.env`. Program paths stay in `.env` on purpose: anyone who could change them could run any program on your server.

Text files, Markdown and ebooks (EPUB, FB2) do not use this step. Chatvia reads them directly.

## Splitting text into chunks

Text files, ebooks and web pages are split into chunks before they are saved.

Choose the splitter under **Settings → Chunking**:

- **Chunker** uses chunk.fit's open-source [chunker](https://github.com/chatvia/chunker). Under **Chunker server**, point the **Address** to your own chunker (no API key), or use the hosted chunk.fit service with its key. The key is only ever sent to the hosted service. The Docker stack runs its own chunker and uses it automatically.
  Under **Strategy** you pick the library (LangChain, LlamaIndex or Chonkie) and its strategy: recursive, sentences, fixed tokens, Markdown headings, or semantic chunks (semantic needs an embeddings model on the chunker). Only the options of that strategy are shown: chunk size, overlap, sizes in tokens or characters, the structure to split by (Markdown, HTML, code), and so on. **Try it** splits a sample with your options before you save them. The default is the recursive splitter with Markdown structure, 400 tokens, no overlap.
- **Built-in** runs inside Chatvia and needs no extra software. It keeps whole paragraphs together, in chunks of about 400 tokens. Change the size under **Built-in splitter**.

The **Tokenizer** (on the same page) counts every size: chunk sizes and the stored size of each chunk. The chunker counts the tokens; when it cannot, Chatvia counts them itself with the same tokenizer.

## Speech to text

Speech to text is used for voice input in chat and for audio and video sources.

- **Scaleway AI (Whisper)** needs the Scaleway AI key; the **Speech-to-text model** (default `whisper-large-v3`) is in the Scaleway AI section.
- **Off** turns the step off. Voice input then answers "Audio transcription is not configured on this server.", and audio and video sources fail with the same message.

Audio is compressed with `ffmpeg` before it is sent. Set `FFMPEG_BIN_PATH` in `.env` if `ffmpeg` is not on the `PATH`.

## Reading whole PDF files

Two features need a model that can read a whole PDF file at once:

- **Document summaries.** Each new source gets a short summary and a list of the questions it answers. This helps search.
- **Chat with PDF files.** Visitors can attach a PDF in the chat and ask questions about it.

| Option | Settings |
| --- | --- |
| Mistral | The Mistral key, and under **Models**: the document summary and analysis models (default `mistral-large-latest`) and the PDF chat model (default `mistral-medium-latest`) |
| Off | Both features are off. New sources get no summary, but search still works on their pages. PDF uploads in chat are refused with a short message, and the chat widget hides its upload button. |

The PDF chat model must support both PDF files and tool calls, so agent tools still work in those chats.
