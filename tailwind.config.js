import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        // Tightened radius scale — roughly half the Tailwind defaults, so every
        // existing `rounded-*` utility gets squarer without touching templates.
        // `full` is left alone: avatars, dots and switch knobs are true circles.
        borderRadius: {
            none: '0',
            sm: '0.0625rem', // 1px
            DEFAULT: '0.125rem', // 2px
            md: '0.1875rem', // 3px
            lg: '0.25rem', // 4px  — buttons, inputs, selects
            xl: '0.375rem', // 6px  — cards, modals, panels
            '2xl': '0.5rem', // 8px
            '3xl': '0.75rem', // 12px
            full: '9999px',
        },

        extend: {
            colors: {
                primary: {
                    DEFAULT: 'rgb(var(--color-primary) / <alpha-value>)',
                    hover: 'rgb(var(--color-primary-hover) / <alpha-value>)',
                    soft: 'rgb(var(--color-primary-soft) / <alpha-value>)',
                },
                accent: {
                    DEFAULT: 'rgb(var(--color-accent) / <alpha-value>)',
                    hover: 'rgb(var(--color-accent-hover) / <alpha-value>)',
                    fg: 'rgb(var(--color-accent-fg) / <alpha-value>)',
                },
                surface: {
                    DEFAULT: 'rgb(var(--color-surface) / <alpha-value>)',
                    hover: 'rgb(var(--color-surface-hover) / <alpha-value>)',
                    sunken: 'rgb(var(--color-surface-sunken) / <alpha-value>)',
                },
                page: 'rgb(var(--color-background) / <alpha-value>)',
                foreground: {
                    DEFAULT: 'rgb(var(--color-text) / <alpha-value>)',
                    secondary: 'rgb(var(--color-text-secondary) / <alpha-value>)',
                    muted: 'rgb(var(--color-text-muted) / <alpha-value>)',
                },
                success: {
                    DEFAULT: 'rgb(var(--color-success) / <alpha-value>)',
                    soft: 'rgb(var(--color-success-soft) / <alpha-value>)',
                },
                warning: {
                    DEFAULT: 'rgb(var(--color-warning) / <alpha-value>)',
                    soft: 'rgb(var(--color-warning-soft) / <alpha-value>)',
                },
                danger: {
                    DEFAULT: 'rgb(var(--color-danger) / <alpha-value>)',
                    hover: 'rgb(var(--color-danger-hover) / <alpha-value>)',
                    soft: 'rgb(var(--color-danger-soft) / <alpha-value>)',
                },
                info: {
                    DEFAULT: 'rgb(var(--color-info) / <alpha-value>)',
                    soft: 'rgb(var(--color-info-soft) / <alpha-value>)',
                },
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            borderColor: {
                DEFAULT: 'rgb(var(--color-border))',
                strong: 'rgb(var(--color-border-strong))',
            },
            boxShadow: {
                xs: 'var(--shadow-xs)',
                sm: 'var(--shadow-sm)',
                DEFAULT: 'var(--shadow-sm)',
                md: 'var(--shadow-md)',
                lg: 'var(--shadow-lg)',
                xl: 'var(--shadow-xl)',
                rim: 'var(--shadow-ring), var(--rim-top)',
                none: 'none',
            },
            ringColor: {
                DEFAULT: 'rgb(var(--color-primary) / 0.25)',
            },
            outlineColor: {
                DEFAULT: 'rgb(var(--color-primary) / 0.25)',
            },
        },
    },

    plugins: [forms],
};
