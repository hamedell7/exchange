<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Market\MarketAssetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/market')->group(function (): void {
    Route::get('/assets', [MarketAssetController::class, 'index'])
        ->name('market.assets.index');
});
