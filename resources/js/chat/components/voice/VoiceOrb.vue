<script setup lang="ts">
/**
 * The voice call's orb: a glowing sphere in the chat's primary color that
 * breathes while idle, swells and ripples with the live audio level (the
 * visitor's microphone while they talk, the agent's voice while it speaks),
 * and slowly swirls while the agent thinks. Drawn on a canvas each frame;
 * with reduced motion it only grows and shrinks gently.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Loudness, 0–1. */
        level?: number;
        mode?: 'idle' | 'listening' | 'thinking' | 'speaking';
        /** Paused (muted): drawn still and faded. */
        dimmed?: boolean;
    }>(),
    { level: 0, mode: 'idle', dimmed: false },
);

const canvas = ref<HTMLCanvasElement | null>(null);

/** Points around the edge; enough for a smooth outline. */
const EDGE_POINTS = 72;

type Rgb = [number, number, number];

let frame: number | null = null;
let time = 0;
let lastTimestamp = 0;
let smoothLevel = 0;
let base: Rgb = [79, 70, 229];
let reducedMotion = false;
let motionQuery: MediaQueryList | null = null;
// The call view shrinks the orb once the transcript starts, so follow the canvas's own size.
let sizeObserver: ResizeObserver | null = null;

/** Turns any CSS color (oklch, hex, rgb…) into RGB by painting one pixel. */
function toRgb(color: string): Rgb | null {
    const probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    if (!probe || !color) return null;

    probe.fillStyle = '#000';
    probe.fillStyle = color;
    probe.fillRect(0, 0, 1, 1);
    const [r, g, b] = probe.getImageData(0, 0, 1, 1).data;

    return [r, g, b];
}

function readColor(): void {
    if (!canvas.value) return;

    const primary = getComputedStyle(canvas.value).getPropertyValue('--primary').trim();
    base = toRgb(primary) ?? base;
}

const mix = (color: Rgb, target: Rgb, amount: number): Rgb =>
    color.map((channel, index) => Math.round(channel + (target[index] - channel) * amount)) as Rgb;
const rgba = ([r, g, b]: Rgb, alpha: number): string => `rgba(${r}, ${g}, ${b}, ${alpha})`;
const WHITE: Rgb = [255, 255, 255];

function resize(): void {
    const element = canvas.value;
    if (!element) return;

    const ratio = window.devicePixelRatio || 1;
    const { width, height } = element.getBoundingClientRect();
    element.width = Math.max(1, Math.round(width * ratio));
    element.height = Math.max(1, Math.round(height * ratio));
}

/** The orb's outline: a circle pushed out by a few travelling waves. */
function outline(context: CanvasRenderingContext2D, cx: number, cy: number, radius: number, wobble: number, phase: number): void {
    const points: [number, number][] = [];

    for (let i = 0; i < EDGE_POINTS; i++) {
        const angle = (i / EDGE_POINTS) * Math.PI * 2;
        const wave = 0.5 * Math.sin(3 * angle + phase * 1.3) + 0.3 * Math.sin(5 * angle - phase * 1.7) + 0.2 * Math.sin(8 * angle + phase * 2.3);
        const r = radius * (1 + wobble * wave);
        points.push([cx + Math.cos(angle) * r, cy + Math.sin(angle) * r]);
    }

    context.beginPath();
    const last = points[points.length - 1];
    context.moveTo((last[0] + points[0][0]) / 2, (last[1] + points[0][1]) / 2);

    for (let i = 0; i < points.length; i++) {
        const [x, y] = points[i];
        const [nx, ny] = points[(i + 1) % points.length];
        context.quadraticCurveTo(x, y, (x + nx) / 2, (y + ny) / 2);
    }

    context.closePath();
}

function draw(timestamp: number): void {
    const element = canvas.value;
    const context = element?.getContext('2d');
    if (!element || !context) return;

    const delta = lastTimestamp ? Math.min(0.05, (timestamp - lastTimestamp) / 1000) : 0.016;
    lastTimestamp = timestamp;

    const target = props.dimmed ? 0 : Math.min(1, props.level * 2.2);
    // Rise fast, fall slowly, so the orb follows speech without flicker.
    smoothLevel += (target - smoothLevel) * (target > smoothLevel ? 0.35 : 0.08);

    const thinking = props.mode === 'thinking';
    const speaking = props.mode === 'speaking';
    time += delta * (reducedMotion ? 0 : thinking ? 1.8 : 1 + smoothLevel * 2.5);

    const { width, height } = element;
    const size = Math.min(width, height);
    const cx = width / 2;
    const cy = height / 2;

    const breathing = reducedMotion ? 0 : Math.sin(time * (thinking ? 2.2 : 1.1)) * (thinking ? 0.035 : 0.02);
    // Sized so the halo fades out inside the canvas at full volume.
    const radius = size * 0.27 * (1 + breathing + smoothLevel * 0.18);
    const wobble = reducedMotion ? 0 : 0.012 + smoothLevel * 0.07 + (thinking ? 0.015 : 0);

    const color = speaking ? mix(base, WHITE, 0.18) : base;
    const light = mix(color, WHITE, 0.55);
    const dark = mix(color, [0, 0, 0], 0.25);

    context.clearRect(0, 0, width, height);
    context.globalAlpha = props.dimmed ? 0.45 : 1;

    // Soft halo behind the orb.
    const halo = context.createRadialGradient(cx, cy, radius * 0.8, cx, cy, size * 0.5);
    halo.addColorStop(0, rgba(color, 0.28 + smoothLevel * 0.3));
    halo.addColorStop(1, rgba(color, 0));
    context.fillStyle = halo;
    context.fillRect(0, 0, width, height);

    // The body: a light core drifting around the centre, deeper at the edge.
    const drift = radius * 0.28;
    const gx = cx + Math.cos(time * 0.7) * drift;
    const gy = cy + Math.sin(time * 0.9) * drift - radius * 0.15;
    const body = context.createRadialGradient(gx, gy, radius * 0.05, cx, cy, radius * 1.15);
    body.addColorStop(0, rgba(light, 1));
    body.addColorStop(0.55, rgba(color, 1));
    body.addColorStop(1, rgba(dark, 1));

    context.save();
    context.shadowColor = rgba(color, 0.55);
    context.shadowBlur = size * (0.05 + smoothLevel * 0.08);
    outline(context, cx, cy, radius, wobble, time);
    context.fillStyle = body;
    context.fill();
    context.restore();

    // A soft inner glow that swirls, for depth.
    const sx = cx + Math.cos(time * 1.3) * radius * 0.12;
    const sy = cy + Math.sin(time * 1.1) * radius * 0.12;
    const swirl = context.createRadialGradient(sx, sy, 0, sx, sy, radius * 0.7);
    swirl.addColorStop(0, rgba(light, 0.3 + smoothLevel * 0.25));
    swirl.addColorStop(1, rgba(light, 0));
    outline(context, sx, sy, radius * 0.7, wobble * 1.4, -time * 1.4);
    context.fillStyle = swirl;
    context.fill();

    // A small gloss highlight.
    const gloss = context.createRadialGradient(cx - radius * 0.35, cy - radius * 0.4, 0, cx - radius * 0.35, cy - radius * 0.4, radius * 0.45);
    gloss.addColorStop(0, 'rgba(255, 255, 255, 0.45)');
    gloss.addColorStop(1, 'rgba(255, 255, 255, 0)');
    context.fillStyle = gloss;
    outline(context, cx, cy, radius, wobble, time);
    context.fill();

    context.globalAlpha = 1;
    frame = requestAnimationFrame(draw);
}

const onMotionChange = (event: MediaQueryListEvent) => (reducedMotion = event.matches);

onMounted(() => {
    motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    reducedMotion = motionQuery.matches;
    motionQuery.addEventListener('change', onMotionChange);

    readColor();
    resize();
    sizeObserver = new ResizeObserver(resize);
    if (canvas.value) sizeObserver.observe(canvas.value);
    frame = requestAnimationFrame(draw);
});

// The chat's theme (light/dark, the agent's color) can change mid-call.
watch(() => props.mode, readColor);

onBeforeUnmount(() => {
    if (frame !== null) cancelAnimationFrame(frame);
    sizeObserver?.disconnect();
    motionQuery?.removeEventListener('change', onMotionChange);
});
</script>

<template>
    <canvas ref="canvas" class="block h-full w-full" aria-hidden="true" />
</template>
