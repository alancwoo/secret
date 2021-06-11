let mix = require('laravel-mix')
require('laravel-mix-purgecss')

mix.js('resources/js/app.js', 'public/js')
    .postCss('resources/css/app.css', 'public/css')
    .options({
        postCss: [
            require('tailwindcss'),
            require('postcss-nested')
        ]
    })
    .purgeCss()
    .browserSync('http://secret.test')
