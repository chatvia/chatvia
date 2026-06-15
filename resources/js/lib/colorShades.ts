// Tailwind exposes only these 11 stops per color (see resources/css/app.css).
// Any other value — e.g. an intermediate 550 produced by an older slider — has
// no `--{theme}-{shade}` custom property, so referencing it yields a blank
// color. Snap to the nearest real stop before building any var(--theme-shade).
export const SHADES: readonly number[] = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950];

export function nearestShade(shade: number): number {
    let nearest = SHADES[0];
    for (const stop of SHADES) {
        if (Math.abs(stop - shade) < Math.abs(nearest - shade)) {
            nearest = stop;
        }
    }
    return nearest;
}
