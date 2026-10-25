// Load test for ChatVia.ai chat replies, through the public API.
//
// Each virtual user starts a conversation with the agent and sends a few
// messages, one after the other, as a visitor would. The run reports how long
// a full reply takes and how many calls failed. See README.md in this folder.
//
//   k6 run -e BASE_URL=https://chatvia.test -e AGENT=<agent external id> \
//          -e TOKENS=<token1,token2,...> docker/load/chat.k6.js
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'https://chatvia.test').replace(/\/$/, '');
const AGENT = __ENV.AGENT;
// The API allows 30 chat messages a minute per token, so spread users over several.
const TOKENS = (__ENV.TOKENS || '').split(',').filter(Boolean);
const QUESTIONS = (__ENV.QUESTIONS || 'What are your opening hours?|How long does delivery take?|Can I return an item?').split('|');

const replyTime = new Trend('chat_reply_ms', true);
const failed = new Rate('chat_failed');

export const options = {
    stages: [
        { duration: __ENV.RAMP || '1m', target: Number(__ENV.USERS || 20) },
        { duration: __ENV.HOLD || '3m', target: Number(__ENV.USERS || 20) },
        { duration: '30s', target: 0 },
    ],
    thresholds: {
        chat_failed: ['rate<0.02'],
        chat_reply_ms: ['p(95)<15000'],
    },
};

export function setup() {
    if (!AGENT || TOKENS.length === 0) {
        throw new Error('Set AGENT and TOKENS (see docker/load/README.md).');
    }
}

function headers(token) {
    return { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json', Accept: 'application/json' };
}

export default function () {
    const token = TOKENS[(__VU - 1) % TOKENS.length];
    const base = `${BASE_URL}/api/v1/agents/${AGENT}/conversations`;

    const started = http.post(base, JSON.stringify({}), { headers: headers(token), tags: { name: 'start conversation' } });
    if (!check(started, { 'conversation started': (r) => r.status === 201 })) {
        failed.add(true);
        sleep(5);
        return;
    }

    const conversation = started.json('data.id');

    for (const question of QUESTIONS) {
        const reply = http.post(`${base}/${conversation}/messages`, JSON.stringify({ message: question }), {
            headers: headers(token),
            tags: { name: 'send message' },
            timeout: '120s',
        });

        const ok = check(reply, { 'reply received': (r) => r.status >= 200 && r.status < 300 });
        failed.add(!ok);
        if (ok) replyTime.add(reply.timings.duration);

        // A visitor reads the answer before asking again.
        sleep(2 + Math.random() * 4);
    }
}
