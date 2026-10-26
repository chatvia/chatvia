/**
 * The step chain of a reply: the live steps while it streams, else the ones
 * saved in its metadata, turned into the shape the chain renders. A stored
 * tool step is always finished.
 */
import type { Message, MessageStep, StoredMessageStep } from '@/types/chat';

export function messageSteps(message: Message): MessageStep[] {
    if (message.steps) return message.steps;

    return (message.metadata?.steps ?? []).flatMap((step, index) => fromStored(step, index));
}

function fromStored(step: StoredMessageStep, index: number): MessageStep[] {
    if (step.kind === 'text') {
        return step.text ? [{ kind: 'text', text: step.text }] : [];
    }

    const label = step.label ?? '';

    return [
        {
            kind: 'tool',
            done: true,
            call: {
                tool_call_id: `stored-${index}`,
                name: label,
                kind: step.tool_kind ?? 'function',
                label,
                activity_label: null,
                icon_url: step.icon_url ?? null,
                brand_color: null,
                started_at: 0,
            },
        },
    ];
}
