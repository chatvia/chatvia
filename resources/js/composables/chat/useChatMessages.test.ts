import { describe, expect, it, vi } from 'vitest';
import { useChatMessages } from './useChatMessages';

vi.mock('@/api/chat', () => ({
    fetchConversationMessages: vi.fn(),
    fetchConversationSources: vi.fn(),
}));

/** A reply's generated image, audio file or video: listed as pending until its job is done, then swapped in place. */
describe('generated media in the transcript', () => {
    it('lists the files still being made', () => {
        const transcript = useChatMessages('c1');
        transcript.addArtifacts('r1', [
            { kind: 'image', data: { id: 'm1', status: 'pending', prompt: 'A red bicycle' } },
            { kind: 'audio', data: { id: 'm2', status: 'ready', url: '/a.mp3' } },
            { kind: 'video', data: { id: 'm3', status: 'pending', prompt: 'Waves at dusk' } },
            { kind: 'echarts_option', data: { series: [] } },
        ]);

        expect(transcript.pendingMediaIds.value).toEqual(['m1', 'm3']);
    });

    it('swaps in the made file and keeps what the tool stored', () => {
        const transcript = useChatMessages('c1');
        transcript.addArtifacts('r1', [{ kind: 'image', data: { id: 'm1', status: 'pending', prompt: 'A red bicycle' } }]);

        transcript.updateMedia({ id: 'm1', kind: 'image', status: 'ready', url: '/m1.png', mime_type: 'image/png', failure: null });

        expect(transcript.pendingMediaIds.value).toEqual([]);
        expect(transcript.artifactsFor({ id: 'r1', role: 'assistant', content: '' })[0].data).toEqual({
            id: 'm1',
            kind: 'image',
            status: 'ready',
            url: '/m1.png',
            mime_type: 'image/png',
            failure: null,
            prompt: 'A red bicycle',
        });
    });

    it('leaves other replies alone', () => {
        const transcript = useChatMessages('c1');
        const chart = { kind: 'echarts_option', data: { series: [] } };
        transcript.addArtifacts('r2', [chart]);

        transcript.updateMedia({ id: 'm1', kind: 'image', status: 'failed', url: null, mime_type: null, failure: 'refused' });

        expect(transcript.artifactsFor({ id: 'r2', role: 'assistant', content: '' })).toEqual([chart]);
    });
});
