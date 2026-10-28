import { settledMedia } from '@/chat/lib/generatedMedia';
import { describe, expect, it } from 'vitest';

/** A reply's generated files: nothing to report until every one is made or failed. */
describe('settledMedia', () => {
    it('waits while a file is still being made', () => {
        expect(
            settledMedia([
                { kind: 'image', data: { id: 'm1', status: 'ready', url: '/m1.png' } },
                { kind: 'video', data: { id: 'm2', status: 'pending' } },
            ]),
        ).toBeNull();
    });

    it('counts a file the tool stored without a status as still being made', () => {
        expect(settledMedia([{ kind: 'video', data: { id: 'm1', prompt: 'Waves at dusk' } }])).toBeNull();
    });

    it('has nothing to say about a reply without generated files', () => {
        expect(settledMedia([{ kind: 'echarts_option', data: { series: [] } }])).toBeNull();
        expect(settledMedia([])).toBeNull();
    });

    it('names the kind when every made file shares it', () => {
        expect(
            settledMedia([
                { kind: 'image', data: { id: 'm1', status: 'ready' } },
                { kind: 'image', data: { id: 'm2', status: 'ready' } },
                { kind: 'video', data: { id: 'm3', status: 'failed', failure: 'refused' } },
                { kind: 'echarts_option', data: { series: [] } },
            ]),
        ).toEqual({ ready: 2, kind: 'image' });
    });

    it('names no kind when the made files differ', () => {
        expect(
            settledMedia([
                { kind: 'image', data: { id: 'm1', status: 'ready' } },
                { kind: 'audio', data: { id: 'm2', status: 'ready' } },
            ]),
        ).toEqual({ ready: 2, kind: null });
    });

    it('reports none made when every file failed', () => {
        expect(settledMedia([{ kind: 'video', data: { id: 'm1', status: 'failed', failure: 'timed_out' } }])).toEqual({ ready: 0, kind: null });
    });
});
