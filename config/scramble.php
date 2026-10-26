<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Scramble — Chatvia Public API documentation
    |--------------------------------------------------------------------------
    |
    | Generates the OpenAPI 3.1 spec for our customer-facing API. Docs live
    | at `/docs/api` and the raw spec is at `/docs/api.json`.
    |
    | We scope discovery to /api/v1/* in ApiDocsServiceProvider via
    | `Scramble::routes(...)` — the `api_path` below is informational only.
    */

    'api_path' => 'api/v1',
    'api_domain' => null,
    'export_path' => 'api.json',

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        // `{base_url}` becomes this install's API address in ApiDocsServiceProvider.
        'description' => <<<'MARKDOWN'
            The ChatVia.ai API lets your own software do what you do in the
            dashboard: chat with an agent, set agents up, add knowledge, manage
            chatters and teams, and read conversations and analytics.

            - **Base URL:** every path in these docs starts at `{base_url}`.
            - **JSON:** request and response bodies are JSON. Send
              `Content-Type: application/json` with a body.
            - **IDs:** records are named by their `id`, a string. Treat it as
              opaque: store it and send it back as it is.

            The sections below go from what you need first to the details:
            [Authentication](#authentication), [Quick start](#quick-start),
            [Chatting with an agent](#chatting-with-an-agent),
            [Streaming](#streaming), [Errors](#errors),
            [Rate limits](#rate-limits) and [Pagination](#pagination). The
            endpoints follow in the menu, grouped by topic.

            ## Authentication

            Every request needs an API key, sent as a bearer token in the
            `Authorization` header:

            ```
            Authorization: Bearer YOUR_API_KEY
            ```

            Create a key in the dashboard under **Workspace → Integrations → API
            keys**. The key is shown only once, so store it somewhere safe.
            Anyone who has it can act as you: keep it on your server, never in a
            web page or an app.

            A key acts as the person who created it, with that person's rights:

            - The workspace owner's key can do everything.
            - A teammate's key can do only what the teammate's role allows in the
              dashboard. Anything else answers `403`.
            - Some actions are for the owner only, such as creating or deleting an
              agent and managing teams.

            A missing or wrong key answers `401`. If your workspace limits access
            by IP address or country, the API follows the same rules.

            ## Quick start

            Three calls take you from an API key to an agent's reply.

            **1. Find your agent.** Copy the `id` of the agent you want.

            ```bash
            curl {base_url}/agents \
              -H "Authorization: Bearer YOUR_API_KEY"
            ```

            **2. Open a conversation.** Copy the `id` from the answer.

            ```bash
            curl -X POST {base_url}/agents/AGENT_ID/conversations \
              -H "Authorization: Bearer YOUR_API_KEY"
            ```

            **3. Send a message.** The agent's answer is in `data.message.content`.

            ```bash
            curl -X POST {base_url}/agents/AGENT_ID/conversations/CONVERSATION_ID/messages \
              -H "Authorization: Bearer YOUR_API_KEY" \
              -H "Content-Type: application/json" \
              -d '{"message": "What are your opening hours?"}'
            ```

            The answer:

            ```json
            {
              "data": {
                "conversation_id": "0199e7c2-5b1e-7c3a-9f0d-3a6e2b8c4d10",
                "message": {
                  "id": "0199e7c2-6a40-7d11-8b2e-1c9f5e7a3b22",
                  "role": "assistant",
                  "content": "We are open from 9:00 to 17:00, Monday to Friday."
                },
                "sources": [],
                "done_reason": "complete",
                "response_time_ms": 1840,
                "error": null
              }
            }
            ```

            Send the next message to the same conversation, and the agent
            remembers what was said before.

            ## Chatting with an agent

            Open a conversation with `POST /agents/{agent}/conversations`, then
            send each message with `POST /agents/{agent}/conversations/{conversation}/messages`.
            The reply comes back as one JSON answer once it is finished. To show
            it while it is being written, turn on [streaming](#streaming).

            - **Chatters:** pass `chatter_id` when you open a conversation to
              link it to one of your chatters. The chatter's monthly message limit
              then applies to its replies.
            - **Safe retries:** send a `request_id` of your own with each message.
              If you send the same `request_id` again within a few minutes, you get
              the finished reply back. No new reply is written, and none is
              counted.
            - **Tools that need approval:** tools that ask a person to approve
              each call are not available over the API. The agent does not see
              them.

            ## Streaming

            Add `"stream": true` to the message body, or send an
            `Accept: text/event-stream` header, to get the reply as server-sent
            events. Each frame is one `data:` line of JSON:

            ```
            data: {"v":1,"seq":3,"id":"<message id>","type":"delta","data":{"text":"Hel"}}
            ```

            `seq` counts up from 0, and `id` is the reply message's id. `type`
            tells you what `data` holds:

            | type | data | meaning |
            |---|---|---|
            | `start` | `user_message_id`, `request_id`, `replayed` | The turn began. |
            | `delta` | `text` | The next piece of the reply. Add it to the end. |
            | `replace` | `text` | A safety check rewrote the reply. Replace everything you have received so far. |
            | `sources` | `sources` | The knowledge sources the reply used. |
            | `tool` | `phase`, `name`, … | The agent is using a tool. |
            | `meta` | `key`, `value` | Extra signals, such as a handoff to a person. |
            | `error` | `code`, `message`, `retryable` | The turn failed. See [Errors](#errors). |
            | `done` | `reason`, `response_time_ms` | The last frame. `reason` is `complete` or `error`. |

            ## Errors

            Every error answer is JSON with a `message`. A validation error also
            lists the problems for each field in `errors`:

            ```json
            {
              "message": "The name field is required.",
              "errors": {
                "name": ["The name field is required."]
              }
            }
            ```

            | Status | Meaning |
            |---|---|
            | `401` | The API key is missing or wrong. |
            | `403` | The key's person is not allowed to do this. |
            | `404` | The record does not exist, or it belongs to another workspace. |
            | `422` | The request is not valid. `errors` says what to fix. |
            | `429` | Too many requests. See [Rate limits](#rate-limits). |
            | `500`, `502` | A problem on the server or at the AI provider. Try again later. |

            **Chat errors** also carry a `code` and a `retryable` flag, in both
            JSON and streaming mode:

            | code | meaning |
            |---|---|
            | `message_limit_reached` | Your workspace used its monthly messages. |
            | `chatter_message_limit_reached` | The chatter used their monthly messages. |
            | `rate_limited` | Too many messages. Try again in a minute. |
            | `upstream_unavailable` | The AI provider did not answer. Try again. |
            | `access_blocked` | A workspace admin blocked this visitor or chatter. |
            | `internal` | Something went wrong on the server. |

            In JSON mode, an error that happens before the reply has any text
            answers with the matching status (`403`, `429`, `502` or `500`). If
            the turn fails after text began, you get a `200` with
            `"done_reason": "error"` and the partial text. That text is already
            saved, so to try again, send a new message, not the same one.

            ## Rate limits

            Each person can make 300 requests a minute, across all of their API
            keys. Sending messages to an agent has its own, lower limit of 30 a
            minute.

            Every answer has `X-RateLimit-Limit` and `X-RateLimit-Remaining`
            headers. Over the limit, the answer is `429` with a `Retry-After`
            header: the number of seconds to wait before you try again.

            ## Pagination

            Every list endpoint returns one page of items, newest first:

            ```json
            {
              "data": ["…"],
              "next_cursor": "eyJpZCI6MTg2LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9"
            }
            ```

            To get the next page, send the `next_cursor` from the answer back as
            the `cursor` query parameter. Leave `cursor` out for the first page.
            On the last page, `next_cursor` is `null`: stop there.

            `per_page` sets how many items a page holds, from 1 to 100 (25 when
            you leave it out). A value outside that range is moved into the
            range, not rejected.

            A cursor marks a fixed place in the list, not a page number. Pages
            stay stable: items created or deleted while you page through do not
            shift what you get. That is also why there are no page numbers and
            no total count.

            Treat the cursor as an opaque string: send back exactly the
            `next_cursor` you received, and never build or change one. A cursor
            the endpoint did not give out starts the list again at the first
            page.
            MARKDOWN,
    ],

    'ui' => [
        // Set from the brand name in ApiDocsServiceProvider.
        'title' => null,
        'theme' => 'light',
        'hide_try_it' => false,
        'hide_schemas' => false,
        'logo' => '',
        'try_it_credentials_policy' => 'include',
        'layout' => 'responsive',
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy' => false,
    'flatten_deep_query_parameters' => true,

    'middleware' => [
        'web',
    ],

    'extensions' => [],
];
