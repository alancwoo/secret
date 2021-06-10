let mix = require('laravel-mix')
mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css', [
        require('tailwindcss'),
        require('postcss-nested')
    ])
    .browserSync('http://secret.test')
