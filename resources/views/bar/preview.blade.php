<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bar preview — {{ $agentName }}</title>
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            background: #f8fafc;
            color: #1a202c;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        .host-nav {
            display: flex;
            align-items: center;
            padding: 1rem 2rem;
            background: white;
            border-bottom: 1px solid rgba(15, 23, 42, 0.06);
        }

        .host-nav .brand {
            font-weight: 600;
            font-size: 0.95rem;
            color: #0f172a;
        }

        .host-hero {
            max-width: 720px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .host-hero h1 {
            font-size: 1.75rem;
            margin: 0 0 1rem;
            color: #0f172a;
            letter-spacing: -0.01em;
        }

        .host-hero p {
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }
    </style>
</head>

<body>
    <nav class="host-nav">
        <div class="brand">▍ Acme Inc.</div>
    </nav>
    <section class="host-hero">
        <h1>Welcome to Acme.</h1>
        <p>
            This is a placeholder host page so you can see how the chat widget appears in the corner of a real customer site.
            Click the floating bubble to open the agent.
        </p>
    </section>

    <script
        id="chatbot-embed-script"
        src="{{ $embedScriptUrl }}"
        data-agent-id="{{ $agentId }}"
        data-mode="bar"
        data-language="{{ $language }}"
        @if (!empty($authToken)) data-auth="{{ $authToken }}" @endif
        @if (!empty($theme)) data-theme="{{ $theme }}" @endif
        defer
    ></script>
</body>

</html>
