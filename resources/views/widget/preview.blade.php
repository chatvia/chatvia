<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Widget Preview</title>
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            /* Prevents scrollbars */
        }

        #chat-container {
            width: 70%;
            height: 90%;
            margin: 0 auto;
            border: solid 1px #ddd;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            #chat-container {
                width: 95%;
                height: 98%;
                border-radius: 4px;
            }
        }

        @media (max-width: 480px) {
            #chat-container {
                width: 100%;
                height: 100%;
                border: none;
                border-radius: 0;
                box-shadow: none;
            }
        }

        /* Minimal "real-page" backdrop shown only in the full-page preview
           (Playground → Widget). Just enough chrome that the floating bubble
           reads as sitting on a customer page rather than a blank canvas. */
        body.host {
            overflow: auto;
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

@php
    $isBar = ($mode ?? 'widget') === 'bar';
    $isFullPreview = request()->get('full-preview') === 'true';
    // In bar mode the widget renders a floating launcher bar; mounting it into
    // a container puts it in flat mode and skips the bar entirely, so we
    // only mount for the bubble widget (and never for the full-page preview).
    $useMount = !$isFullPreview && !$isBar;
@endphp

<body class="{{ $isFullPreview && !$isBar ? 'host' : '' }}">
    @if ($useMount)
        <div id="chat-container"></div>
    @elseif ($isFullPreview && !$isBar)
        {{-- Simple host-page backdrop. The floating bubble pins itself to the
             corner via its configured position; everything below is just so it
             has something to sit on. --}}
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
    @endif

    <script id="chatbot-embed-script" src="{{ $embedScriptUrl }}" data-agent-id="{{ $agentId }}"
        @if ($useMount) data-mount-id="chat-container" @endif data-language="{{ $language }}"
        @if (!empty($authToken)) data-auth="{{ $authToken }}" @endif
        @if (!empty($theme)) data-theme="{{ $theme }}" @endif
        @if ($isBar) data-mode="bar" @endif defer></script>
</body>

</html>
