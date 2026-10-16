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
        'description' => <<<'MARKDOWN'
            Every request carries your bearer token in the `Authorization` header.

            ## Pagination

            Every list endpoint returns one page of items, newest first:

            ```json
            {
              "data": ["…"],
              "next_cursor": "eyJpZCI6MTg2LCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9"
            }
            ```

            To get the next page, send the `next_cursor` of the response back as the
            `cursor` query parameter. Leave `cursor` out for the first page. On the
            last page `next_cursor` is `null`: stop there.

            `per_page` sets how many items a page holds, from 1 to 100 (25 when left
            out). A value outside that range is brought into range, not rejected.

            A cursor marks a fixed place in the list, not a page number. Pages stay
            stable — items created or deleted while you page through never shift
            what you get — and a late page costs the server no more than the first.
            That is also why there are no page numbers and no total count.

            Treat the cursor as an opaque string: send back exactly the
            `next_cursor` you received, and never build or edit one. A cursor the
            endpoint did not hand out starts the list again at the first page.

            ## Chatting with an agent

            Open a conversation with `POST /v1/agents/{id}/conversations`, then
            send each message with `POST …/conversations/{id}/messages`. By
            default the reply comes back as one JSON response once it is
            finished. Add `stream: true` to the body (or send an
            `Accept: text/event-stream` header) to receive it as server-sent
            events instead.

            Send a `request_id` of your own with each message to make it safe
            to retry: resending the same value within a few minutes replays the
            finished reply instead of generating — and counting — a new one.

            Tool calls that need a person's approval are not available over the
            API; the agent simply does not see those tools.

            ## Streaming

            With streaming on, the response is a `text/event-stream`. Each
            frame is one `data:` line of JSON:

            ```
            data: {"v":1,"seq":3,"id":"<message id>","type":"delta","data":{"text":"Hel"}}
            ```

            `seq` counts up from 0 and `id` is the reply message's id. `type`
            tells you what `data` holds:

            | type | data | meaning |
            |---|---|---|
            | `start` | `user_message_id`, `request_id`, `replayed` | the turn began |
            | `delta` | `text` | the next piece of the reply — append it |
            | `replace` | `text` | a safety check rewrote the reply — replace everything received so far |
            | `sources` | `sources` | the knowledge sources the reply cited |
            | `tool` | `phase`, `name`, … | the agent is using a tool |
            | `meta` | `key`, `value` | extra signals, such as a handoff to a human |
            | `error` | `code`, `message`, `retryable` | the turn failed — see the codes below |
            | `done` | `reason`, `response_time_ms` | the last frame; `reason` is `complete` or `error` |

            The error codes, in both modes: `message_limit_reached` and
            `chatter_message_limit_reached` (the monthly cap), `rate_limited`
            (try again in a minute), `upstream_unavailable` (the AI provider;
            retry), `access_blocked`, and `internal`. The JSON mode answers an
            error that happened before any text with the matching HTTP status
            (403, 429, 502 or 500); a turn that failed after text began is a
            200 with `done_reason: "error"` and the partial text, which is
            already stored — retry with a new message, not a repeat.
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
