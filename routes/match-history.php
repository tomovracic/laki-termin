<?php

use App\Http\Controllers\MatchHistory\PlayedMatchController;
use App\Http\Controllers\Users\UserSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('played-matches', [PlayedMatchController::class, 'store'])->name('played-matches.store');
    Route::patch('played-matches/{playedMatch}', [PlayedMatchController::class, 'update'])->name('played-matches.update');
    Route::delete('played-matches/{playedMatch}', [PlayedMatchController::class, 'destroy'])->name('played-matches.destroy');
    Route::get('users/search', UserSearchController::class)->name('users.search');
});
