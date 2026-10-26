import type { Message } from '@/types/chat';
import { describe, expect, it } from 'vitest';
import { messageSteps } from './messageSteps';

function reply(fields: Partial<Message>): Message {
    return { role: 'assistant', content: 'Done.', id: 'm1', ...fields };
}

describe('messageSteps', () => {
    it('reads the steps saved in the metadata as a finished chain', () => {
        const steps = messageSteps(
            reply({
                metadata: {
                    steps: [
                        { kind: 'text', text: 'Let me look up your event types.' },
                        { kind: 'tool', label: 'Cal.com', tool_kind: 'mcp', icon_url: '/images/logos/connectors/cal.svg' },
                    ],
                },
            }),
        );

        expect(steps).toHaveLength(2);
        expect(steps[0]).toEqual({ kind: 'text', text: 'Let me look up your event types.' });
        expect(steps[1]).toMatchObject({
            kind: 'tool',
            done: true,
            call: { label: 'Cal.com', kind: 'mcp', icon_url: '/images/logos/connectors/cal.svg' },
        });
    });

    it('prefers the live steps of a streaming reply', () => {
        const live = [{ kind: 'text' as const, text: 'Checking…' }];

        expect(messageSteps(reply({ steps: live, metadata: { steps: [{ kind: 'text', text: 'Old' }] } }))).toBe(live);
    });

    it('skips empty text and treats a missing tool kind as a function', () => {
        const steps = messageSteps(
            reply({
                metadata: {
                    steps: [
                        { kind: 'text', text: '' },
                        { kind: 'tool', label: 'Webhook' },
                    ],
                },
            }),
        );

        expect(steps).toHaveLength(1);
        expect(steps[0]).toMatchObject({ kind: 'tool', call: { kind: 'function', icon_url: null } });
    });

    it('has no steps for a plain reply', () => {
        expect(messageSteps(reply({}))).toEqual([]);
    });
});
