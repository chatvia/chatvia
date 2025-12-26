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
    </style>
</head>

<body>

    @if (request()->get('full-preview') === 'true')
        <script id="chatbot-embed-script" src="{{ $embedScriptUrl }}" agent-id="{{ $agentId }}"
            language="{{ $language }}" defer></script>
    @else
        <div id="chat-container"></div>


        <script
            id="chatbot-embed-script"
            src="{{ $embedScriptUrl }}"
            agent-id="{{ $agentId }}"
            mount-id="chat-container"
            language="{{ $language }}" defer>
        </script>
    @endif
</body>

</html>
