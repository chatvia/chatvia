<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Widget Preview</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            line-height: 1.6;
        }

        .container {
            max-width: none;
            margin: 0;
            background: white;
            padding: 20px;
            border-radius: 0;
            box-shadow: none;
            min-height: 100vh;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
        }

        p {
            color: #666;
            margin-bottom: 20px;
        }

        .demo-content {
            margin: 40px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Widget Preview</h1>
        <p>This is a preview page to test the ChatVia widget integration. The widget should appear in the bottom-right corner of the page.</p>
    </div>

    <div id="chat-container"></div>


    <script
        id="chatbot-embed-script"
        src="{{ $embedScriptUrl }}"
        agent-id="{{ $agentId }}"
        async>
    </script>

</body>
</html>
