<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\EventSlotTemplateController;
use App\Http\Controllers\Api\FerryController;
use App\Http\Controllers\Api\FerryScheduleTemplateController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\MapLocationController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\ThemeParkController;
use App\Http\Controllers\Api\ThemeParkTicketController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public: active promotions and map locations for the homepage.
Route::get('api/promotions', [PromotionController::class, 'index']);
Route::get('api/map/locations', [MapLocationController::class, 'index']);

// Browsable without any session: hotels/rooms, ferries/schedules, theme park
// events. Anonymous visitors can shop before an account exists.
Route::prefix('api')->group(function () {
    Route::get('hotels/popular', [HotelController::class, 'popular']);
    Route::get('hotels', [HotelController::class, 'index']);
    Route::get('hotels/{hotel}', [HotelController::class, 'show']);
    Route::get('hotels/{hotel}/rooms', [RoomController::class, 'index']);
    Route::get('hotels/{hotel}/room-types', [RoomTypeController::class, 'index']);

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

// First step of guest checkout: each of these provisions and logs in a
// placeholder account when the visitor isn't authenticated yet (see
// GuestSession::ensure), then behaves like any other booking.
//
// Throttled because they are the only unauthenticated endpoints in the
// application that can write to the users table. Provisioning happens inside the
// controller, after validation - as route middleware it ran first and so a
// malformed body still left a permanent user, role and session row behind.
Route::middleware('throttle:writes')->prefix('api')->group(function () {
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

    // ---------------------------------------------------------------------
    // Owner-scoped routes. No role gate: who may act is a property of the
    // record, not of the caller's role, so the controller compares user_id (and
    // allows the relevant staff role as an override). A role gate here would
    // either lock out the owner or let every visitor at every record.
    // ---------------------------------------------------------------------
    Route::prefix('api')->group(function () {
        Route::patch('guest/claim', [GuestController::class, 'claim']);
        // Verifies a password, so it is throttled like any other credential
        // endpoint - this was a second, unlimited path into every account.
        Route::post('guest/login', [GuestController::class, 'login'])
            ->middleware('throttle:auth');

        Route::get('bookings', [BookingController::class, 'index']);
        // Declared before bookings/{booking} so the literal path is not captured
        // by the wildcard.
        Route::post('bookings/pay', [BookingController::class, 'pay'])
            ->middleware('throttle:writes');
        Route::get('bookings/{booking}', [BookingController::class, 'show']);
        Route::patch('bookings/{booking}', [BookingController::class, 'update']);

        Route::get('ferry/tickets', [FerryController::class, 'myTickets']);
        Route::post('ferry/tickets', [FerryController::class, 'issueTicket'])
            ->middleware('throttle:writes');

        Route::get('themepark/bookings', [ThemeParkController::class, 'myBookings']);
        Route::delete('themepark/bookings/{booking}', [ThemeParkController::class, 'cancelBooking']);
    });

    // ---------------------------------------------------------------------
    // Role-gated routes.
    //
    // The gate is declared here rather than left to each controller body. It used
    // to live only in the controllers - 24 inline hasAnyRole() checks - which
    // means a new route was one forgotten line away from being public, and there
    // was nothing to review against. The in-controller checks remain as a second
    // layer; AuthorizationMatrixTest asserts both.
    // ---------------------------------------------------------------------

    // Hotels and their rooms.
    Route::prefix('api')->middleware('role:hotel_manager|admin')->group(function () {
        Route::post('hotels', [HotelController::class, 'store']);
        Route::patch('hotels/{hotel}', [HotelController::class, 'update']);
        Route::delete('hotels/{hotel}', [HotelController::class, 'destroy']);

        Route::post('hotels/{hotel}/rooms', [RoomController::class, 'store']);
        Route::patch('rooms/{room}', [RoomController::class, 'update']);
        Route::delete('rooms/{room}', [RoomController::class, 'destroy']);

        Route::post('hotels/{hotel}/room-types', [RoomTypeController::class, 'store']);
        Route::patch('room-types/{roomType}', [RoomTypeController::class, 'update']);
        Route::delete('room-types/{roomType}', [RoomTypeController::class, 'destroy']);

        // {media} stays a plain integer rather than a bound Media model: binding
        // would fetch an image belonging to any record before the owning hotel or
        // room type has been checked.
        Route::post('hotels/{hotel}/gallery', [HotelController::class, 'storeGallery']);
        Route::delete('hotels/{hotel}/gallery/{media}', [HotelController::class, 'destroyGalleryImage']);
        Route::post('room-types/{roomType}/gallery', [RoomTypeController::class, 'storeGallery']);
        Route::delete('room-types/{roomType}/gallery/{media}', [RoomTypeController::class, 'destroyGalleryImage']);
    });

    // The fleet, its sailings, and the gate. Reading the ferry list stays public
    // (visitors need boat names on their tickets); reshaping boats does not.
    Route::prefix('api')->middleware('role:ferry_operator|admin')->group(function () {
        Route::post('ferries', [FerryController::class, 'storeFerry']);
        Route::patch('ferries/{ferry}', [FerryController::class, 'updateFerry']);
        Route::delete('ferries/{ferry}', [FerryController::class, 'destroyFerry']);

        Route::post('ferry/schedules', [FerryController::class, 'storeSchedule']);
        Route::patch('ferry/schedules/{schedule}', [FerryController::class, 'updateSchedule']);
        Route::delete('ferry/schedules/{schedule}', [FerryController::class, 'destroySchedule']);
        Route::get('ferry/schedules/{schedule}/passengers', [FerryController::class, 'passengers']);

        Route::get('ferry/schedule-templates', [FerryScheduleTemplateController::class, 'index']);
        Route::post('ferry/schedule-templates', [FerryScheduleTemplateController::class, 'store']);
        Route::patch('ferry/schedule-templates/{scheduleTemplate}', [FerryScheduleTemplateController::class, 'update']);
        Route::delete('ferry/schedule-templates/{scheduleTemplate}', [FerryScheduleTemplateController::class, 'destroy']);

        // Scan resolution. Literal paths first, or the {ticket}/{booking}
        // wildcards below capture them.
        Route::get('ferry/tickets/lookup', [FerryController::class, 'lookupTicketByReference']);
        Route::get('ferry/bookings/lookup', [FerryController::class, 'lookupBookingByReference']);
        Route::post('ferry/tickets/walkup', [FerryController::class, 'issueWalkupTicket']);
        Route::get('ferry/bookings/{booking}/party', [FerryController::class, 'partyStatus']);
        Route::get('ferry/tickets/{ticket}', [FerryController::class, 'showTicket']);
        Route::post('ferry/tickets/{ticket}/validate', [FerryController::class, 'validateTicket']);
        Route::post('ferry/tickets/{ticket}/cancel', [FerryController::class, 'cancelTicket']);
    });

    // Theme park operations.
    Route::prefix('api')->middleware('role:themepark_staff|admin')->group(function () {
        // Every event's slots together, for the staff scheduling calendar. This
        // sat in the public group, which published the schedule of unannounced
        // events to anyone who asked.
        Route::get('themepark/slots', [ThemeParkController::class, 'allSlots']);

        Route::post('themepark/events', [ThemeParkController::class, 'store']);
        Route::patch('themepark/events/{event}', [ThemeParkController::class, 'update']);
        Route::delete('themepark/events/{event}', [ThemeParkController::class, 'destroy']);
        Route::post('themepark/events/{event}/gallery', [ThemeParkController::class, 'storeGallery']);
        Route::delete('themepark/events/{event}/gallery/{media}', [ThemeParkController::class, 'destroyGalleryImage']);
        Route::post('themepark/events/{event}/slots', [ThemeParkController::class, 'storeSlot']);
        Route::patch('themepark/slots/{slot}', [ThemeParkController::class, 'updateSlot']);
        Route::delete('themepark/slots/{slot}', [ThemeParkController::class, 'destroySlot']);

        Route::get('themepark/slot-templates', [EventSlotTemplateController::class, 'index']);
        Route::post('themepark/slot-templates', [EventSlotTemplateController::class, 'store']);
        Route::patch('themepark/slot-templates/{slotTemplate}', [EventSlotTemplateController::class, 'update']);
        Route::delete('themepark/slot-templates/{slotTemplate}', [EventSlotTemplateController::class, 'destroy']);

        Route::post('themepark/tickets/sell', [ThemeParkTicketController::class, 'sellTicket']);
        // Before themepark/tickets/{booking}, or the wildcard swallows it.
        Route::get('themepark/tickets/lookup', [ThemeParkTicketController::class, 'lookupByReference']);
        Route::get('themepark/tickets/{booking}', [ThemeParkTicketController::class, 'showTicket']);
        Route::post('themepark/tickets/{booking}/validate', [ThemeParkTicketController::class, 'validateTicket']);
        Route::get('themepark/reports/sales', [ThemeParkTicketController::class, 'dailySales']);
        Route::get('themepark/capacity', [ThemeParkTicketController::class, 'capacityStatus']);
    });

    // Promotions are written by whoever runs the thing being promoted; the
    // controller further restricts editing to the promotion's own author.
    Route::prefix('api')
        ->middleware('role:hotel_manager|themepark_staff|ferry_operator|admin')
        ->group(function () {
            Route::get('promotions/manage', [PromotionController::class, 'manage']);
            Route::post('promotions', [PromotionController::class, 'store']);
            Route::patch('promotions/{promotion}', [PromotionController::class, 'update']);
            Route::delete('promotions/{promotion}', [PromotionController::class, 'destroy']);
        });

    // Island map and user administration.
    Route::prefix('api')->middleware('role:admin')->group(function () {
        Route::get('map/locations/manage', [MapLocationController::class, 'manage']);
        Route::post('map/locations', [MapLocationController::class, 'store']);
        Route::patch('map/locations/{mapLocation}', [MapLocationController::class, 'update']);
        Route::delete('map/locations/{mapLocation}', [MapLocationController::class, 'destroy']);

        Route::get('admin/stats', [AdminController::class, 'stats']);
        Route::get('admin/users', [AdminController::class, 'index']);
        Route::post('admin/users', [AdminController::class, 'store']);
        Route::patch('admin/users/{user}', [AdminController::class, 'update']);
        Route::delete('admin/users/{user}', [AdminController::class, 'destroy']);
    });
});

require __DIR__.'/auth.php';

// All remaining GET requests serve the SPA shell; vue-router takes over client-side.
Route::view('/{any?}', 'app')
    ->where('any', '^(?!api/).*$')
    ->name('spa');
