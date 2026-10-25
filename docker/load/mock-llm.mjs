// A stand-in AI provider for load tests: answers OpenAI-compatible chat
// completions (plain or streamed) and embeddings after a short, random wait,
// so a load test measures ChatVia.ai and costs nothing. Node 20+, no packages.
//
//   node docker/load/mock-llm.mjs            # listens on :8089
//   PORT=9000 DELAY_MS=800 DIMENSIONS=1024 node docker/load/mock-llm.mjs
import { createServer } from 'node:http';

const PORT = Number(process.env.PORT || 8089);
const DELAY_MS = Number(process.env.DELAY_MS || 600);
const DIMENSIONS = Number(process.env.DIMENSIONS || 1024);
const ANSWER = 'This is a test answer from the load-test model. It has a few sentences, like a real reply would.';

const wait = () => new Promise((resolve) => setTimeout(resolve, DELAY_MS * (0.5 + Math.random())));
const usage = { prompt_tokens: 200, completion_tokens: 30, total_tokens: 230 };

async function body(request) {
    let text = '';
    for await (const part of request) text += part;
    try {
        return JSON.parse(text || '{}');
    } catch {
        return {};
    }
}

createServer(async (request, response) => {
    const payload = await body(request);
    await wait();

    if (request.url.endsWith('/embeddings')) {
        const inputs = Array.isArray(payload.input) ? payload.input : [payload.input];
        const data = inputs.map((_, index) => ({ object: 'embedding', index, embedding: Array.from({ length: DIMENSIONS }, () => Math.random() - 0.5) }));
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ object: 'list', data, model: payload.model, usage }));
        return;
    }

    if (request.url.endsWith('/chat/completions')) {
        if (payload.stream) {
            response.writeHead(200, { 'Content-Type': 'text/event-stream' });
            for (const word of ANSWER.split(' ')) {
                response.write(`data: ${JSON.stringify({ choices: [{ index: 0, delta: { content: word + ' ' } }] })}\n\n`);
                await new Promise((resolve) => setTimeout(resolve, 20));
            }
            response.write(`data: ${JSON.stringify({ choices: [{ index: 0, delta: {}, finish_reason: 'stop' }], usage })}\n\n`);
            response.end('data: [DONE]\n\n');
            return;
        }

        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ choices: [{ index: 0, message: { role: 'assistant', content: ANSWER }, finish_reason: 'stop' }], usage }));
        return;
    }

    response.writeHead(404, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({ error: { message: `No mock for ${request.url}` } }));
}).listen(PORT, () => console.log(`Mock AI provider on http://localhost:${PORT}/v1`));
