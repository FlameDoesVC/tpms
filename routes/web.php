<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/api/user', function (Request $request) {
        return $request->user();
    });

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// All remaining GET requests serve the SPA shell; vue-router takes over client-side.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api/).*$')
    ->name('spa');
