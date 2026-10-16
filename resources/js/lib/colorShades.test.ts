import { describe, expect, it } from 'vitest';
import { nearestShade, SHADES } from './colorShades';

// Only the 11 Tailwind stops have a --{theme}-{shade} custom property; any
// other value would give a blank colour, so it snaps to the nearest stop.
describe('nearestShade', () => {
    it('keeps a real stop as it is', () => {
        for (const stop of SHADES) {
            expect(nearestShade(stop)).toBe(stop);
        }
    });

    it('snaps a value between stops to the nearest one, the lower one on a tie', () => {
        expect(nearestShade(540)).toBe(500);
        expect(nearestShade(560)).toBe(600);
        expect(nearestShade(550)).toBe(500);
        expect(nearestShade(75)).toBe(50);
    });

    it('snaps a value outside the range to the first or last stop', () => {
        expect(nearestShade(0)).toBe(50);
        expect(nearestShade(1200)).toBe(950);
    });
});
