# YouTube sources

Add a YouTube video URL and Chatvia indexes its transcript.

![SCREENSHOT — youtube sources](/screenshots/sources-youtube.png)

## Adding a video

In **Sources → YouTube**, paste the video URL and click **Add**. Supported URL forms:

- `https://www.youtube.com/watch?v=…`
- `https://youtu.be/…`
- `https://www.youtube.com/embed/…`

Playlists are not supported — add videos one at a time.

## How transcription works

Chatvia pulls the official transcript from YouTube (if available) and falls back to running speech-to-text on the audio track if there isn't one. The transcript is chunked the same way as any other text — but timestamps are preserved on each chunk, so:

- Citations include a deep link to the moment in the video.
- The Playground shows the timestamp of the retrieved chunks.

::: tip Captions matter
Videos with creator-uploaded captions index more cleanly than auto-captioned ones. If you control the source, take a few minutes to upload a corrected transcript on YouTube — it pays off.
:::

## Limits

- Max video length: 4 hours per video (most plans).
- Max videos per agent depends on plan.
- Live streams and members-only videos are not supported.

## Metadata

Each YouTube source carries:

- Title, channel, thumbnail.
- Duration.
- Detected language.
- Retrieval count.

## When to use this vs. a Web source

If a video has a companion blog post or transcript on the same domain, indexing the **web page** is usually cheaper and easier to keep updated. YouTube sources are best when the video itself is the canonical content — recorded talks, screencasts, podcast interviews.

## Removing a video

Deleting a YouTube source removes the transcript and its chunks. The original video on YouTube is untouched.
