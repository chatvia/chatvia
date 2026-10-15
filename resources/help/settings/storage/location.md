Chooses where this app keeps its files: knowledge files, backups, chat attachments, and the images from knowledge files that agents show in answers.

- **Store files in** — **This server** keeps the files on the server's own disk and needs no account. **S3-compatible storage** keeps the files at a storage service that works like Amazon S3, for example AWS S3, Scaleway, Cloudflare R2 or MinIO.

With **S3-compatible storage**, more fields show:

- **Endpoint** — The service's address. Leave it empty for AWS S3.
- **Region** — Where your storage is, for example fr-par or eu-west-1. Use auto for Cloudflare R2.
- **Access key ID** and **Secret access key** — The key pair you create at the service. The secret is stored encrypted and never shown again. Leave it blank to keep it.
- **Bucket** — The bucket (a named storage space) for every file of the app. Keep it private: the app opens files through its own signed links.
- Under **Advanced**: **Use path-style addresses**. MinIO and some other services need it. Leave it off unless your service needs it.

**Switching moves no files.** Files you already have stay in the old place, and the app can no longer find them. Choose before people add files, or copy the files to the new place first.

**Mistral OCR** downloads each document from a temporary link. With **This server**, that works only when this site is reachable from the internet.

**Tip:** After you save, click **Test the connection** at the top of the page. It writes, reads and deletes a small test file.
