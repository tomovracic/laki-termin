<?php

use App\Http\Controllers\Admin\AdminTerrainOverviewController;
use App\Http\Controllers\Admin\AdminUserOverviewController;
use App\Http\Controllers\Dashboard\UserDashboardController;
use App\Http\Controllers\Dashboard\UserTerrainReservationPageController;
use App\Http\Controllers\Settings\LocaleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');
Route::post('locale', LocaleController::class)->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', UserDashboardController::class)->name('dashboard');
    Route::get('dashboard/availability', [UserDashboardController::class, 'availability'])->name('dashboard.availability');
    Route::get('dashboard/terrains/{terrain}', UserTerrainReservationPageController::class)->name('dashboard.terrains.show');
    Route::get('dashboard/terrains/{terrain}/slots', [UserTerrainReservationPageController::class, 'slots'])->name('dashboard.terrains.slots');
    Route::redirect('admin', 'admin/users')->name('admin.index');
    Route::get('admin/users', AdminUserOverviewController::class)->name('admin.users');
    Route::get('admin/terrains', AdminTerrainOverviewController::class)->name('admin.terrains');
    Route::redirect('admin/management', 'admin/users')->name('admin.management');
});

require __DIR__.'/settings.php';
require __DIR__.'/reservations.php';
