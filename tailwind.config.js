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
            colors: {
                primary: { 50: '#ebf7ff', 100: '#d1edff', 200: '#a8deff', 300: '#70c8ff', 400: '#33b1ff', 500: '#006db1', 600: '#005488', 700: '#003455', 800: '#012338', 900: '#01121c', 950: '#000b12' },
                accent: { 50: '#ecf8fe', 100: '#d4f0fc', 200: '#aee3fa', 300: '#79d0f6', 400: '#40bcf2', 500: '#0e96d1', 600: '#0b7bab', 700: '#08587b', 800: '#05364b', 900: '#021b25' },
                ink: { 950: '#0b0e12', 900: '#12161c', 800: '#1c2129' },
                neutral: { 50: '#f6f8fa', 100: '#eef1f4', 200: '#dde3e9', 300: '#c3cdd6', 400: '#98a5b1', 500: '#71818f', 600: '#556370', 700: '#414c56', 800: '#2b323a', 900: '#1a1f24' },
                secondary: { 100: '#eef1f4', 500: '#71818f', 700: '#414c56' },
                success: { 100: '#dcfce7', 500: '#16a34a', 700: '#15803d' },
                warning: { 100: '#fef3c7', 500: '#d97706', 700: '#b45309' },
                danger: { 100: '#fee2e2', 500: '#dc2626', 700: '#b91c1c' },
                info: { 100: '#d4f0fc', 500: '#0e96d1', 700: '#08587b' },
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
