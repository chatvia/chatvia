# YouTube sources

Add a YouTube video URL and Chatvia indexes its transcript. Useful for recorded all-hands, product demos, training sessions, and onboarding videos your team already publishes.

![SCREENSHOT — youtube sources](/screenshots/sources-youtube.png)

## Adding a video

In the agent's **Sources → YouTube** tab, paste the video URL and click **Add**. Supported URL forms:

- `https://www.youtube.com/watch?v=…`
- `https://youtu.be/…`
- `https://www.youtube.com/embed/…`

Playlists are not supported — add videos one at a time.

## How transcription works

Chatvia pulls the official transcript from YouTube (if available) and falls back to running speech-to-text on the audio track if there isn't one. The transcript is chunked the same way as any other text — but timestamps are preserved on each chunk, so:

- Citations include a deep link to the moment in the video.
- The Playground shows the timestamp of the retrieved chunks.

::: tip Captions matter
Videos with team-uploaded captions index more cleanly than auto-captioned ones. If your workspace owns the channel, take a few minutes to upload a corrected transcript on YouTube — it pays off across every agent that reuses the source.
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

If a video has a companion blog post or transcript on the same domain, indexing the **web page** is usually cheaper and easier to keep updated. YouTube sources are best when the video itself is the canonical content — recorded all-hands, product demos, conference talks, customer training, podcast interviews.

## Removing a video

Any teammate with access to the agent can delete a YouTube source. Deleting removes the transcript and its chunks from the agent. The original video on YouTube is untouched.
