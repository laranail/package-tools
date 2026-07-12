<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// fixture route file loaded when its gate defaults to true
Route::get('/pkg-default-on', static fn (): string => 'default-on')->name('pkg.default-on');
