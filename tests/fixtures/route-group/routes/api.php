<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/ping', static fn (): string => 'pong')->name('ping');
