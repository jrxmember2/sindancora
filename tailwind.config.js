import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            keyframes: {
                blob: {
                    '0%, 100%': { transform: 'translate(0px, 0px) scale(1)' },
                    '33%': { transform: 'translate(24px, -36px) scale(1.12)' },
                    '66%': { transform: 'translate(-20px, 22px) scale(0.94)' },
                },
                floaty: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-10px)' },
                },
                gradientShift: {
                    '0%, 100%': { backgroundPosition: '0% 50%' },
                    '50%': { backgroundPosition: '100% 50%' },
                },
                spinSlow: {
                    to: { transform: 'rotate(360deg)' },
                },
                fadeUp: {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                blob: 'blob 14s ease-in-out infinite',
                floaty: 'floaty 6s ease-in-out infinite',
                'gradient-shift': 'gradientShift 14s ease infinite',
                'spin-slow': 'spinSlow 45s linear infinite',
                'fade-up': 'fadeUp 0.6s ease-out both',
            },
        },
    },

    plugins: [forms],
};
