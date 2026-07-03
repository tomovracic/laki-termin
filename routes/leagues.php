<?php

use App\Http\Controllers\Leagues\LeagueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('leagues', [LeagueController::class, 'store'])->name('leagues.store');
    Route::post('leagues/{league}/participants', [LeagueController::class, 'storeParticipant'])->name('leagues.participants.store');
    Route::patch('leagues/{league}/matches/{match}/result', [LeagueController::class, 'updateMatchResult'])->name('leagues.matches.result.update');
});
