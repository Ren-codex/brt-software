/**
 * The system theme: one chosen colour, turned into the full set of theme
 * variables the stylesheets read (see scss/components/_theme-vars.scss).
 *
 * A user picks a single colour. Everything else — the hover shade,
 * the heading ink, the pale header tints, the borders — is worked out from it
 * here, keeping each one at the same lightness and strength the original green
 * palette used. That is what lets any colour be chosen without someone having
 * to design nine matching shades by hand, and keeps text readable whatever is
 * picked.
 *
 * With no theme set, nothing is overridden and the stylesheet's own values —
 * the original green, exactly — apply.
 */

import { THEME_FAMILY } from './themeFamily.js';

const STORAGE_KEY = 'brt_theme_primary';

/** Ready-made themes offered in the switcher. The first is the default. */
export const THEME_PRESETS = [
    { name: 'BRT Green', color: null, swatch: '#3d8d7a' },
    { name: 'Ocean Blue', color: '#2f6fb5', swatch: '#2f6fb5' },
    { name: 'Royal Indigo', color: '#4f5bd5', swatch: '#4f5bd5' },
    { name: 'Plum', color: '#8e4f9f', swatch: '#8e4f9f' },
    { name: 'Rosewood', color: '#b0495a', swatch: '#b0495a' },
    { name: 'Rice Gold', color: '#966c17', swatch: '#966c17' },
    { name: 'Terracotta', color: '#b15e38', swatch: '#b15e38' },
    { name: 'Slate', color: '#49647a', swatch: '#49647a' },
];

export function isHexColor(value) {
    return typeof value === 'string' && /^#[0-9a-fA-F]{6}$/.test(value);
}

function hexToRgb(hex) {
    const n = parseInt(hex.slice(1), 16);
    return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

function rgbToHsl([r, g, b]) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    let h = 0;
    let s = 0;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        if (max === r) h = (g - b) / d + (g < b ? 6 : 0);
        else if (max === g) h = (b - r) / d + 2;
        else h = (r - g) / d + 4;
        h *= 60;
    }

    return [h, s * 100, l * 100];
}

function hslToRgb(h, s, l) {
    s /= 100; l /= 100;
    const k = n => (n + h / 30) % 12;
    const a = s * Math.min(l, 1 - l);
    const f = n => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
    return [f(0), f(8), f(4)].map(v => Math.round(v * 255));
}

/** WCAG contrast ratio between a colour and white. */
function contrastWithWhite(rgb) {
    const [r, g, b] = rgb.map(v => {
        const c = v / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });

    return 1.05 / (0.2126 * r + 0.7152 * g + 0.0722 * b + 0.05);
}

const toHex = rgb => '#' + rgb.map(v => v.toString(16).padStart(2, '0')).join('');
const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

/**
 * The variables for a chosen colour.
 *
 * The chosen colour is first brought into a workable range — not so pale that
 * white text on a button disappears, not so dark that hover has nowhere to go.
 * The supporting shades then copy the lightness and saturation of the original
 * palette's shades, with only the hue swapped.
 */
export function themeVariables(color) {
    const [h, s, l] = rgbToHsl(hexToRgb(color));

    // A grey stays grey; anything with colour in it is kept within a range
    // that is neither washed out nor garish.
    const primaryS = s < 8 ? s : clamp(s, 25, 75);

    // White text sits on this colour, on every button. So it is darkened, a
    // step at a time, until white on it meets the 4.5:1 contrast that normal
    // text needs to be readable — which matters most for yellows and golds,
    // where a colour can look strong and still drown white text.
    let primaryL = clamp(l, 22, 60);
    while (primaryL > 22 && contrastWithWhite(hslToRgb(h, primaryS, primaryL)) < 4.5) {
        primaryL -= 1;
    }

    const shade = (sat, light) => hslToRgb(h, sat, light);
    const primary = shade(primaryS, primaryL);
    const deepS = primaryS < 8 ? primaryS : clamp(primaryS + 20, 0, 90);
    const deepL = clamp(primaryL - 16, 12, 100);
    const deep = shade(deepS, deepL);

    return {
        '--brt-primary': toHex(primary),
        '--brt-primary-rgb': primary.join(', '),
        '--brt-primary-hover': toHex(shade(primaryS, primaryL - 5.5)),
        '--brt-primary-dark': toHex(shade(primaryS, primaryL - 10)),
        '--brt-ink': toHex(shade(Math.min(primaryS, 40), 14)),
        '--brt-tint-1': toHex(shade(Math.min(primaryS, 24), 84)),
        '--brt-tint-2': toHex(shade(Math.min(primaryS, 34), 95)),
        '--brt-border': toHex(shade(Math.min(primaryS, 22), 81)),
        '--brt-muted': toHex(shade(Math.min(primaryS, 14), 48)),
        '--brt-focus': toHex(primary),
        '--brt-focus-rgb': primary.join(', '),
        // Every other shade of the old green in the stylesheets, re-tinted.
        ...familyShades(h, primaryS),
        // The highlighted item in the left menu. The menu's background is the
        // deep shade below, so these are set relative to it — each a fixed
        // step lighter — which keeps the highlight standing out from the menu
        // whatever colour is chosen.
        ...menuHighlight(h, primaryS, deepL),
        // Bootstrap's own primary, which links, badges and .text-primary use.
        '--vz-primary': toHex(deep),
        '--vz-primary-rgb': deep.join(', '),
        '--vz-link-color': toHex(deep),
        '--vz-link-color-rgb': deep.join(', '),
        '--vz-link-hover-color': toHex(shade(primaryS, clamp(primaryL - 22, 10, 100))),
        '--vz-primary-text-emphasis': toHex(shade(primaryS, clamp(primaryL - 20, 10, 100))),
        '--vz-primary-bg-subtle': toHex(shade(Math.min(primaryS, 34), 92)),
        '--vz-primary-border-subtle': toHex(shade(Math.min(primaryS, 30), 78)),
    };
}

/**
 * The supporting shades — hundreds of pale tints, muted labels and dark inks
 * that were each written as their own green — moved onto the chosen colour.
 *
 * Each keeps its own lightness, which is what makes one a background and
 * another a heading; only the hue is swapped. Its strength is scaled by how
 * vivid the chosen colour is compared with the original green, so a grey theme
 * gives greys and a vivid one gives tints with some life in them.
 */
function familyShades(h, s) {
    const strength = s < 8 ? 0 : clamp(s / 40, 0.6, 1.7);
    const out = {};

    THEME_FAMILY.forEach(hex => {
        const [, fs, fl] = rgbToHsl(hexToRgb('#' + hex));
        const rgb = hslToRgb(h, clamp(fs * strength, 0, 92), fl);
        out[`--brt-c-${hex}`] = toHex(rgb);
        out[`--brt-c-${hex}-rgb`] = rgb.join(', ');
    });

    return out;
}

/** The left menu's active-item colours, stepped up from the menu background. */
function menuHighlight(h, s, menuL) {
    const from = hslToRgb(h, s, clamp(menuL + 7, 0, 60));
    const to = hslToRgb(h, s, clamp(menuL + 20, 0, 68));
    const dot = hslToRgb(h, s * 0.8, clamp(menuL + 30, 0, 74));
    // A grey theme keeps a grey glow; forcing colour into it would tint it
    // with whatever hue a grey happens to carry.
    const glow = hslToRgb(h, s < 8 ? s : Math.max(s, 55), 66);

    return {
        '--brt-menu-from': toHex(from),
        '--brt-menu-from-rgb': from.join(', '),
        '--brt-menu-to': toHex(to),
        '--brt-menu-to-rgb': to.join(', '),
        '--brt-menu-dot': toHex(dot),
        '--brt-menu-dot-rgb': dot.join(', '),
        '--brt-menu-glow': toHex(glow),
        '--brt-menu-glow-rgb': glow.join(', '),
    };
}

/**
 * Apply a theme to the page, or with no colour, return to the default.
 *
 * Set on <html> as inline custom properties, which outrank the stylesheet's
 * :root values. The data attribute lets the stylesheet opt further rules in
 * only when a theme is active (see _theme-vars.scss).
 */
export function applyTheme(color) {
    const root = document.documentElement;
    const names = Object.keys(themeVariables('#3d8d7a'));

    if (!isHexColor(color)) {
        names.forEach(name => root.style.removeProperty(name));
        root.removeAttribute('data-brt-theme');
        return;
    }

    Object.entries(themeVariables(color)).forEach(([name, value]) => root.style.setProperty(name, value));
    root.setAttribute('data-brt-theme', 'custom');
}

/**
 * Apply the signed-in user's theme as the app starts.
 *
 * Inertia puts the first page's data, shared props included, in the `data-page`
 * attribute of the root element. Reading the theme from there means it is on
 * the page before Vue has drawn anything. Being the server's own answer for
 * this user, it also cannot be a leftover from whoever used the browser last —
 * which matters on a shared counter computer.
 */
export function applyInitialTheme() {
    try {
        const page = JSON.parse(document.getElementById('app')?.dataset?.page || '{}');
        applyTheme(page?.props?.theme?.primary);
        // An earlier version kept a copy in browser storage; clear it.
        localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Unreadable page data: stay on the default until the app applies it.
    }
}
