let mix = require('laravel-mix')
// mix.js('resources/app.js', 'dist').setPublicPath('dist')
mix.postCss('resources/css/app.css', 'public/css', [
    require('tailwindcss'),
])
    .browserSync('http://secret.test')
