import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

/**
 * Categorical series colours, in fixed order. A module keeps its hue wherever
 * it appears, and a filter that drops a series never repaints the survivors.
 *
 * Checked with the dataviz palette validator against BOTH of this app's card
 * surfaces - light #ffffff and dark #121214 - and all six checks pass on each:
 * lightness band, chroma floor, CVD separation (worst adjacent pair ΔE 14.2
 * protan), normal-vision floor (22.8), and 3:1 contrast. One set therefore
 * serves both themes, which is why a series doesn't change hue when the theme
 * flips. The teal is a step up from the UI's own primary, which measured below
 * the chroma floor and read as grey once it was a thin line on a chart.
 */
export const SERIES_COLORS = {
    hotel: '#0d9488',
    ferry: '#c2640c',
    park: '#0284c7',
};

/**
 * Chart.js paints to a canvas, so it can't inherit CSS variables - the theme
 * has to be read out and handed over, and re-read when the theme changes.
 */
export function useChartTheme() {
    const { isDark } = useTheme();

    const cssVar = (name) =>
        getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return computed(() => {
        // Referenced so the computed re-runs on a theme flip.
        void isDark.value;
        return {
            ink: `rgb(${cssVar('--color-text')})`,
            label: `rgb(${cssVar('--color-text-muted')})`,
            grid: `rgb(${cssVar('--color-border')})`,
            surface: `rgb(${cssVar('--color-surface')})`,
            primary: `rgb(${cssVar('--color-primary')})`,
        };
    });
}
