<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    {{-- The PDF maker may be a browser (Cloudflare, Gotenberg): it may load
         nothing, so a link in a message cannot make it fetch an address.
         WeasyPrint gets the same rule from config/laravel-pdf.php. --}}
    <meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src data:; font-src data:; style-src 'unsafe-inline'">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Conversation Export</title>
    <style>
        {{-- The PDF maker may load nothing, so the font is embedded instead
             of fetched from a font CDN. --}}
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            src: url('data:font/woff2;base64,{{ base64_encode((string) file_get_contents(public_path('fonts/inter/inter-latin-wght-normal.woff2'))) }}') format('woff2');
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            line-height: 1.7;
            color: #1a202c;
            background: #ffffff;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 25px 25px;
        }

        /* ChatMessage Styles */
        .messages {
            margin-top: 0;
        }

        .message {
            margin-bottom: 35px;
            page-break-inside: avoid;
        }

        .message-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .message-role {
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 8px 14px;
            border-radius: 20px;
        }

        .message-role.user {
            background: #3b82f6;
            color: white;
        }

        .message-role.assistant {
            background: #10b981;
            color: white;
        }

        .message-timestamp {
            font-size: 13px;
            color: #94a3b8;
            font-weight: 400;
        }

        .message-model {
            font-size: 11px;
            color: #64748b;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .message-content {
            background: #ffffff;
            padding: 20px 0;
            margin-left: 0;
            line-height: 1.8;
        }

        .message.user .message-content {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
        }

        .message.assistant .message-content {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
        }

        /* Typography */
        .message-content p {
            margin-bottom: 18px;
            line-height: 1.8;
            font-size: 16px;
        }

        .message-content p:last-child {
            margin-bottom: 0;
        }

        .message-content h1,
        .message-content h2,
        .message-content h3,
        .message-content h4,
        .message-content h5,
        .message-content h6 {
            font-weight: 700;
            margin-bottom: 14px;
            margin-top: 28px;
            color: #1a202c;
            line-height: 1.3;
        }

        .message-content h1:first-child,
        .message-content h2:first-child,
        .message-content h3:first-child,
        .message-content h4:first-child,
        .message-content h5:first-child,
        .message-content h6:first-child {
            margin-top: 0;
        }

        .message-content h1 { font-size: 24px; }
        .message-content h2 { font-size: 22px; }
        .message-content h3 { font-size: 20px; }
        .message-content h4 { font-size: 18px; }
        .message-content h5 { font-size: 17px; }
        .message-content h6 { font-size: 16px; }

        .message-content ul,
        .message-content ol {
            margin-left: 24px;
            margin-bottom: 18px;
            line-height: 1.8;
        }

        .message-content li {
            margin-bottom: 6px;
            line-height: 1.8;
            font-size: 16px;
        }

        .message-content blockquote {
            border-left: 4px solid #64748b;
            padding-left: 20px;
            margin: 24px 0;
            font-style: italic;
            color: #64748b;
            line-height: 1.8;
            background: #f8fafc;
            padding: 18px 20px;
            border-radius: 4px;
            font-size: 16px;
        }

        .message-content code {
            background: #f1f5f9;
            padding: 3px 6px;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            color: #dc2626;
            font-weight: 500;
        }

        .message-content pre {
            background: #1e293b;
            color: #f1f5f9;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            margin: 24px 0;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            line-height: 1.6;
        }

        .message-content pre code {
            background: none;
            padding: 0;
            color: inherit;
        }

        .message-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 14px;
        }

        .message-content th,
        .message-content td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .message-content th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }

        /* Page Break Utilities */
        .page-break {
            page-break-before: always;
        }

        .no-break {
            page-break-inside: avoid;
        }

        /* Print Optimizations */
        @media print {
            body {
                font-size: 14px;
            }

            .container {
                padding: 20px 25px;
            }

            .message-content {
                break-inside: avoid;
            }

            /* Page counter support with page numbers */
            @page {
                margin: 12mm;

                @bottom-center {
                    content: "Page " counter(page) " of " counter(pages);
                    font-family: 'Inter', sans-serif;
                    font-size: 10px;
                    color: #94a3b8;
                }
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ChatMessage Content -->
        <div class="messages">
            <div class="message-content no-break">
                {{-- Each message arrives as HTML from the controller. --}}
                @foreach ($messages as $message)
                    {!! $message['html'] !!}

                    <!-- Strategic page break for very long content -->
                    @if(strlen(strip_tags($message['html'])) > 5000)
                        @pageBreak
                    @endif

                    @if ($message['role'] === 'assistant')
                        <hr/>
                    @endif
                @endforeach
            </div>

        </div>
    </div>
</body>
</html>
