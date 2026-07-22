<?php

use App\Http\Controllers\Admin\AdminCancelledReservationsReportController;
use App\Http\Controllers\Admin\AdminLeagueOverviewController;
use App\Http\Controllers\Admin\AdminLeagueShowController;
use App\Http\Controllers\Admin\AdminLoginReportController;
use App\Http\Controllers\Admin\AdminReportsOverviewController;
use App\Http\Controllers\Admin\AdminReservedReservationsReportController;
use App\Http\Controllers\Admin\AdminTerrainOverviewController;
use App\Http\Controllers\Admin\AdminUserOverviewController;
use App\Http\Controllers\Admin\AdminUserReservationsController;
use App\Http\Controllers\Auth\InvitationRegistrationController;
use App\Http\Controllers\Dashboard\UserDashboardController;
use App\Http\Controllers\Dashboard\UserEloRankingController;
use App\Http\Controllers\Dashboard\UserLeagueIndexController;
use App\Http\Controllers\Dashboard\UserLeagueShowController;
use App\Http\Controllers\Dashboard\UserMatchHistoryController;
use App\Http\Controllers\Dashboard\UserReservationsController;
use App\Http\Controllers\Dashboard\UserTerrainReservationPageController;
use App\Http\Controllers\Settings\LocaleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');
Route::post('locale', LocaleController::class)->name('locale.update');
Route::get('invitation/{token}', [InvitationRegistrationController::class, 'show'])->name('invitation.accept');
Route::post('invitation/{token}', [InvitationRegistrationController::class, 'store'])->name('invitation.register');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', UserDashboardController::class)->name('dashboard');
    Route::get('dashboard/reservations', UserReservationsController::class)->name('dashboard.reservations');
    Route::get('dashboard/availability', [UserDashboardController::class, 'availability'])->name('dashboard.availability');
    Route::get('dashboard/terrains/{terrain}', UserTerrainReservationPageController::class)->name('dashboard.terrains.show');
    Route::get('dashboard/terrains/{terrain}/slots', [UserTerrainReservationPageController::class, 'slots'])->name('dashboard.terrains.slots');
    Route::get('dashboard/leagues', UserLeagueIndexController::class)->name('dashboard.leagues');
    Route::get('dashboard/leagues/{league}', UserLeagueShowController::class)->name('dashboard.leagues.show');
    Route::get('dashboard/match-history', UserMatchHistoryController::class)->name('dashboard.match-history');
    Route::get('dashboard/ranking', UserEloRankingController::class)->name('dashboard.ranking');
    Route::redirect('admin', 'admin/users')->name('admin.index');
    Route::get('admin/users', AdminUserOverviewController::class)->name('admin.users');
    Route::get('admin/users/{user}/reservations', AdminUserReservationsController::class)->name('admin.users.reservations');
    Route::get('admin/terrains', AdminTerrainOverviewController::class)->name('admin.terrains');
    Route::get('admin/leagues', AdminLeagueOverviewController::class)->name('admin.leagues');
    Route::get('admin/leagues/{league}', AdminLeagueShowController::class)->name('admin.leagues.show');
    Route::get('admin/reports', AdminReportsOverviewController::class)->name('admin.reports');
    Route::get('admin/reports/logins', AdminLoginReportController::class)->name('admin.reports.logins');
    Route::get('admin/reports/reserved', AdminReservedReservationsReportController::class)->name('admin.reports.reserved');
    Route::get('admin/reports/cancelled', AdminCancelledReservationsReportController::class)->name('admin.reports.cancelled');
    Route::redirect('admin/management', 'admin/users')->name('admin.management');
});

require __DIR__.'/settings.php';
require __DIR__.'/reservations.php';
require __DIR__.'/leagues.php';
require __DIR__.'/match-history.php';
