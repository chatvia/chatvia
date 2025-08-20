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

        <div class="demo-content">
            <h2>Demo Content</h2>
            <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>

            <p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

            <h3>Features</h3>
            <ul>
                <li>Real-time chat functionality</li>
                <li>Customizable appearance</li>
                <li>Easy integration</li>
                <li>Mobile responsive</li>
            </ul>
        </div>

        <p>Try clicking the chat widget in the bottom-right corner to test the functionality.</p>
    </div>

    <script>
        const script = document.createElement('script');
        script.id = 'chatbot-embed-script';
        script.src = '{{ $embedScriptUrl  }}';
        script.async = true;

        script.setAttribute('agent-id', '{{ $agentId }}');
        script.setAttribute('client-id', '{{ $agentId }}');

        // Optional props for customization
        script.setAttribute('data-position', 'bottom-right'); // or 'bottom-left'
        script.setAttribute('data-theme', 'light'); // or 'dark'

        document.body.appendChild(script);
    </script>
</body>
</html>
