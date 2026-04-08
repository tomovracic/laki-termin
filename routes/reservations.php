<?php

use App\Http\Controllers\Reservations\ReservationController;
use App\Http\Controllers\TerrainInactivePeriods\TerrainInactivePeriodController;
use App\Http\Controllers\Terrains\TerrainController;
use App\Http\Controllers\TerrainSettings\TerrainSettingController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('terrains', [TerrainController::class, 'index'])->name('terrains.index');
    Route::post('terrains', [TerrainController::class, 'store'])->name('terrains.store');
    Route::patch('terrains/{terrain}', [TerrainController::class, 'update'])->name('terrains.update');

    Route::post('terrain-settings/upsert', [TerrainSettingController::class, 'upsert'])->name('terrain-settings.upsert');

    Route::get('terrain-inactive-periods', [TerrainInactivePeriodController::class, 'index'])->name('terrain-inactive-periods.index');
    Route::post('terrain-inactive-periods', [TerrainInactivePeriodController::class, 'store'])->name('terrain-inactive-periods.store');
    Route::delete('terrain-inactive-periods/{terrainInactivePeriod}', [TerrainInactivePeriodController::class, 'destroy'])->name('terrain-inactive-periods.destroy');

    Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::post('reservations/bulk', [ReservationController::class, 'storeBulk'])->name('reservations.bulk-store');
    Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/token-count', [UserController::class, 'updateTokenCount'])->name('users.token-count.update');
});
