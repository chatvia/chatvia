<?php

namespace App\Http\Controllers\Widget;

use App\Support\OpenRouter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WidgetStreamController
{
    public function __invoke(Request $request)
    {
        return new StreamedResponse(function () use ($request) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');

            flush();

            if (ob_get_level()) {
                ob_end_clean();
            }

            // Set up client cancellation detection
            ignore_user_abort(false);

            try {
                // Get message from request or use default
                $message = $request->input('message', 'Hey');
                $model = $request->input('model', 'gpt-4o-mini');

                // Prepare messages for OpenRouter
                $llmMessages = [
                    ['role' => 'user', 'content' => $message],
                ];

                // Make OpenRouter request
                $response = app(OpenRouter::class)->stream($model, $llmMessages);

                // Process the stream response
                $body = $response->getBody();
                $buffer = '';

                while (! $body->eof() && connection_status() === CONNECTION_NORMAL) {
                    $chunk = $body->read(1024);
                    if ($chunk === '') {
                        continue;
                    }

                    $buffer .= $chunk;
                    $lines = explode("\n", $buffer);
                    $buffer = array_pop($lines); // Keep incomplete line in buffer

                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) {
                            continue;
                        }

                        // Parse SSE data line
                        if (str_starts_with($line, 'data: ')) {
                            $data = substr($line, 6);

                            if ($data === '[DONE]') {
                                // Send completion signal
                                echo 'data: '.json_encode(['content' => '[DONE]'])."\n\n";
                                flush();
                                break;
                            }

                            try {
                                $decoded = json_decode($data, true);
                                if (isset($decoded['choices'][0]['delta']['content'])) {
                                    $content = $decoded['choices'][0]['delta']['content'];

                                    // Send content chunk
                                    echo 'data: '.json_encode(['content' => $content], JSON_THROW_ON_ERROR)."\n\n";
                                    flush();
                                }
                            } catch (\Exception $e) {
                                // Skip malformed JSON
                                continue;
                            }
                        }
                    }

                    // Check if client disconnected
                    if (connection_aborted()) {
                        break;
                    }
                }

            } catch (\Exception $e) {
                // Send error message
                echo 'data: '.json_encode([
                    'content' => 'Sorry, I encountered an error while processing your message. Please try again.',
                    'error' => true,
                ], JSON_THROW_ON_ERROR)."\n\n";
                flush();
            }

            // Send final done signal if not already sent
            echo 'data: '.json_encode(['content' => '[DONE]'], JSON_THROW_ON_ERROR)."\n\n";
            flush();

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
