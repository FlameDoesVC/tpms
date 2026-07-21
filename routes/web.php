<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/api/user', function (Request $request) {
        return $request->user()->load('roles');
    });

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('api')->group(function () {
        Route::get('hotels', [HotelController::class, 'index']);
        Route::post('hotels', [HotelController::class, 'store']);
        Route::get('hotels/{hotel}', [HotelController::class, 'show']);
        Route::patch('hotels/{hotel}', [HotelController::class, 'update']);
        Route::delete('hotels/{hotel}', [HotelController::class, 'destroy']);

        Route::get('hotels/{hotel}/rooms', [RoomController::class, 'index']);
        Route::post('hotels/{hotel}/rooms', [RoomController::class, 'store']);
        Route::patch('rooms/{room}', [RoomController::class, 'update']);
        Route::delete('rooms/{room}', [RoomController::class, 'destroy']);

        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::post('bookings', [BookingController::class, 'store']);
        Route::patch('bookings/{booking}', [BookingController::class, 'update']);
    });
});

require __DIR__.'/auth.php';

// All remaining GET requests serve the SPA shell; vue-router takes over client-side.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api/).*$')
    ->name('spa');
