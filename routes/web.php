<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FerryController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\ThemeParkController;
use App\Http\Controllers\Api\ThemeParkTicketController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\AutoLoginGuest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Browsable without any session: hotels/rooms, ferries/schedules, theme park
// events. Anonymous visitors can shop before an account exists.
Route::prefix('api')->group(function () {
    Route::get('hotels/popular', [HotelController::class, 'popular']);
    Route::get('hotels', [HotelController::class, 'index']);
    Route::get('hotels/{hotel}', [HotelController::class, 'show']);
    Route::get('hotels/{hotel}/rooms', [RoomController::class, 'index']);
    Route::get('hotels/{hotel}/room-types', [RoomController::class, 'types']);

    Route::get('ferries', [FerryController::class, 'ferries']);
    Route::get('ferry/schedules', [FerryController::class, 'schedules']);
    // Read-only capacity view - a guest-checkout visitor may pick seats for
    // their cart before an account exists (only actually purchasing requires one).
    Route::get('ferry/schedules/{schedule}/seats', [FerryController::class, 'seats']);

    Route::get('themepark/events/popular', [ThemeParkController::class, 'popular']);
    Route::get('themepark/events', [ThemeParkController::class, 'index']);
    Route::get('themepark/events/{event}', [ThemeParkController::class, 'show']);
    // Read-only slot/capacity view - the theme park browsing page needs this
    // for anonymous guest-checkout visitors too, same as the events list above.
    Route::get('themepark/events/{event}/slots', [ThemeParkController::class, 'slots']);
});

// First step of guest checkout: provisions+logs in a placeholder account if
// the visitor isn't authenticated yet, then behaves like any other booking.
Route::middleware(AutoLoginGuest::class)->prefix('api')->group(function () {
    Route::post('bookings', [BookingController::class, 'store']);
    Route::post('themepark/bookings', [ThemeParkController::class, 'bookSlot']);
    Route::post('cart/checkout', [CartController::class, 'checkout']);
});

Route::middleware('auth')->group(function () {
    Route::get('/api/user', function (Request $request) {
        return $request->user()->load('roles');
    });

    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('api')->group(function () {
        Route::patch('guest/claim', [GuestController::class, 'claim']);
        Route::post('guest/login', [GuestController::class, 'login']);

        Route::post('hotels', [HotelController::class, 'store']);
        Route::patch('hotels/{hotel}', [HotelController::class, 'update']);
        Route::delete('hotels/{hotel}', [HotelController::class, 'destroy']);

        Route::post('hotels/{hotel}/rooms', [RoomController::class, 'store']);
        Route::patch('rooms/{room}', [RoomController::class, 'update']);
        Route::delete('rooms/{room}', [RoomController::class, 'destroy']);

        Route::get('bookings', [BookingController::class, 'index']);
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::patch('bookings/{booking}', [BookingController::class, 'update']);

        Route::post('ferry/schedules', [FerryController::class, 'storeSchedule']);
        Route::patch('ferry/schedules/{schedule}', [FerryController::class, 'updateSchedule']);
        Route::delete('ferry/schedules/{schedule}', [FerryController::class, 'destroySchedule']);
        Route::get('ferry/schedules/{schedule}/passengers', [FerryController::class, 'passengers']);

        Route::get('ferry/tickets', [FerryController::class, 'myTickets']);
        Route::post('ferry/tickets', [FerryController::class, 'issueTicket']);
        Route::get('ferry/tickets/{ticket}', [FerryController::class, 'showTicket']);
        Route::post('ferry/tickets/{ticket}/validate', [FerryController::class, 'validateTicket']);
        Route::post('ferry/tickets/{ticket}/cancel', [FerryController::class, 'cancelTicket']);

        Route::post('themepark/events', [ThemeParkController::class, 'store']);
        Route::patch('themepark/events/{event}', [ThemeParkController::class, 'update']);
        Route::delete('themepark/events/{event}', [ThemeParkController::class, 'destroy']);
        Route::post('themepark/events/{event}/slots', [ThemeParkController::class, 'storeSlot']);

        Route::get('themepark/bookings', [ThemeParkController::class, 'myBookings']);
        Route::delete('themepark/bookings/{booking}', [ThemeParkController::class, 'cancelBooking']);

        Route::post('themepark/tickets/sell', [ThemeParkTicketController::class, 'sellTicket']);
        Route::get('themepark/tickets/{booking}', [ThemeParkTicketController::class, 'showTicket']);
        Route::post('themepark/tickets/{booking}/validate', [ThemeParkTicketController::class, 'validateTicket']);
        Route::get('themepark/reports/sales', [ThemeParkTicketController::class, 'dailySales']);
        Route::get('themepark/capacity', [ThemeParkTicketController::class, 'capacityStatus']);
    });
});

require __DIR__.'/auth.php';

// All remaining GET requests serve the SPA shell; vue-router takes over client-side.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api/).*$')
    ->name('spa');
