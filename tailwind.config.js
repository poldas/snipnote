/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './templates/**/*.html.twig',
        './assets/**/*.{js,ts}',
        './tailwind/**/*.{css,js}',
    ],
    theme: {
        extend: {
            screens: {
                'xs': '480px',
            },
        },
    },
    plugins: [],
};

