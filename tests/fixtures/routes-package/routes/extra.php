<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// fixture route file for the hasRoutesWhen() feature tests
Route::get('/pkg-extra', static fn (): string => 'extra')->name('pkg.extra');
