<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\OgMeta\Http\Controllers\OgImageController;

Route::get('/og/{resolverKey}/{identifier}', OgImageController::class)
    ->name('og-meta.image')
    ->where('identifier', '.*');
