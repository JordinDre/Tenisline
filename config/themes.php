<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Theme mode
    |--------------------------------------------------------------------------
    |
    | This option determines how the theme will be set for the application.
    | By default global mode is set to use one theme for all users. If you
    | want to set a theme for each user separately, then set to 'user'.
    |
    */

    'mode' => 'global',

    /*
    |--------------------------------------------------------------------------
    | Theme Icon
    |--------------------------------------------------------------------------
    */

    'icon' => 'heroicon-o-swatch',

    /*
    |--------------------------------------------------------------------------
    | Default Theme
    |--------------------------------------------------------------------------
    */

    'default' => [
        'theme' => 'default',
        // Naranja de la marca. Un admin puede elegir otro en la página Temas.
        'theme_color' => \Filament\Support\Colors\Color::hex('#ff4f17'),
    ],
];
