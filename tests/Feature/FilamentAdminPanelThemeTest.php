<?php

use Filament\Facades\Filament;

it('registers the custom Vite theme for the admin panel', function () {
    expect(Filament::getPanel('admin')->getViteTheme())
        ->toBe('resources/css/filament/admin/theme.css');
});
