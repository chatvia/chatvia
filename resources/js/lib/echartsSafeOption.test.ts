import { describe, expect, it } from 'vitest';
import { safeEchartsOption } from './echartsSafeOption';

// A chart option is tool output: nothing in it may write markup into the
// page, open a script URL or load an outside image.
describe('safeEchartsOption', () => {
    it('renders every tooltip as rich text, never HTML', () => {
        const option = safeEchartsOption({ tooltip: { trigger: 'item', renderMode: 'html' } });

        expect(option.tooltip).toEqual({ trigger: 'item', renderMode: 'richText' });
        expect(safeEchartsOption({ tooltip: [{ renderMode: 'html' }] }).tooltip).toEqual([{ renderMode: 'richText' }]);
    });

    it('removes a formatter that could hold markup, keeping plain templates', () => {
        const option = safeEchartsOption({
            tooltip: { formatter: '<img src=x onerror=alert(1)>' },
            series: [{ type: 'pie', label: { formatter: '{b}: {d}%' } }],
        });

        expect(option.tooltip).toEqual({ renderMode: 'richText' });
        expect(option.series).toEqual([{ type: 'pie', label: { formatter: '{b}: {d}%' } }]);
    });

    it('removes links, rich text, graphics and the toolbox anywhere', () => {
        const option = safeEchartsOption({
            title: { text: 'Sales', link: 'javascript:alert(1)', sublink: 'https://evil.test', target: 'self' },
            series: [{ type: 'bar', label: { rich: { a: { backgroundColor: { image: 'https://evil.test/p.png' } } } } }],
            graphic: [{ type: 'image', style: { image: 'https://evil.test/p.png' } }],
            toolbox: { feature: { dataView: {} } },
        });

        expect(option).toEqual({
            title: { text: 'Sales', target: 'self' },
            series: [{ type: 'bar', label: {} }],
        });
    });

    it('removes image and script addresses from any value', () => {
        const option = safeEchartsOption({ series: [{ type: 'scatter', symbol: 'image://https://evil.test/p.png', name: 'ok' }] });

        expect(option.series).toEqual([{ type: 'scatter', name: 'ok' }]);
    });

    it('does not change the option it was given', () => {
        const original = { tooltip: { renderMode: 'html' } };

        safeEchartsOption(original);

        expect(original).toEqual({ tooltip: { renderMode: 'html' } });
    });
});
