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
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // Design tokens (resources/css/design-tokens.css) mapped to utility
            // classes, so components reference tokens instead of hex literals.
            // Spacing and touch heights aren't remapped here: Tailwind's default
            // scale already lands on the same pixel values (p-4 = 16px = --space-4,
            // h-12 = 48px = --touch-min, h-14 = 56px = --touch-primary) — see
            // .ai/rules/design-system.md.
            colors: {
                'surface-base': 'var(--surface-base)',
                'surface-raised': 'var(--surface-raised)',
                'surface-overlay': 'var(--surface-overlay)',
                accent: 'var(--accent)',
                'accent-muted': 'var(--accent-muted)',
                'accent-glow': 'var(--accent-glow)',
                'accent-text-strong': 'var(--accent-text-strong)',
                'accent-text-soft': 'var(--accent-text-soft)',
                'accent-label': 'var(--accent-label)',
                success: 'var(--success)',
                danger: 'var(--danger)',
                warning: 'var(--warning)',
                'text-on-accent': 'var(--text-on-accent)',
                'text-primary': 'var(--text-primary)',
                'text-secondary': 'var(--text-secondary)',
                'text-body': 'var(--text-body)',
                'text-tertiary': 'var(--text-tertiary)',
                'text-numeric': 'var(--text-numeric)',
                'border-subtle': 'var(--border-subtle)',
                'border-accent': 'var(--border-accent)',
            },
            borderRadius: {
                'radius-sm': 'var(--radius-sm)',
                'radius-md': 'var(--radius-md)',
                'radius-lg': 'var(--radius-lg)',
                'radius-full': 'var(--radius-full)',
            },
            backgroundImage: {
                'gradient-suggestion': 'var(--gradient-suggestion)',
            },
        },
    },

    plugins: [forms],
};
