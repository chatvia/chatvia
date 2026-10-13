Mistral reads documents with OCR (reading the text in scans and images), reads whole PDF files, and can make embeddings (number codes that let the agent find text by meaning).

- **API key** — Create it in the Mistral console ("Open console"). It is stored encrypted and never shown again. Leave the field blank to keep the saved key. With a key, "Automatic" document reading and whole-PDF reading use Mistral. Without one, the local tools read documents (text only) and whole-PDF reading is off.
- **Address** — The Mistral web address. Leave it blank for the default.
- **OCR model** — The model that reads PDFs, office files and images.
- **Embedding model** — Used when Embeddings is "Mistral" on the Services card. It is locked once sources are trained.

The whole-PDF reading models are on the Models card. This key does not add chat models; Mistral chat models are set up separately, as a chat provider.

**Tip:** Leave the model fields blank to use the defaults shown in them.
