<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\FerryTicketService;
use App\Services\HotelBookingService;
use App\Services\ThemeParkBookingService;
use App\Support\GuestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        private HotelBookingService $hotelBookings,
        private FerryTicketService $ferryTickets,
        private ThemeParkBookingService $themeParkBookings,
    ) {}

    /**
     * Everything the visitor added to their (client-side only) cart is
     * created here in one go, inside a single DB transaction - if any item
     * fails, nothing from this request is committed, hotel bookings included.
     */
    public function checkout(Request $request): JsonResponse
    {
        // Validated only for the top-level shape here - $request->validate()
        // would otherwise strip every field down to just id/type, since Laravel
        // only returns whatever was declared in the rule set. Each item's own
        // fields are validated separately, per type, against the raw input below.
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'string'],
            'items.*.type' => ['required', 'in:hotel,ferry,themepark'],
        ]);

        $items = $request->input('items');
        // After the top-level shape has validated, so a malformed cart cannot
        // provision a permanent guest account.
        $actor = GuestSession::ensure($request);
        $userId = $actor->id;

        $created = DB::transaction(function () use ($items, $userId, $actor) {
            // A ferry item can point at a hotel room still in the cart rather
            // than a real booking id - that hotel item always appears earlier
            // in this same array (it had to already exist in the cart to be
            // picked when the ticket was added), so its real booking id is
            // already known by the time we reach the dependent ferry item.
            // A multi-room purchase (quantity > 1) becomes several booking
            // rows sharing one group_booking_id - any one of them works here,
            // since FerryTicketService validates against the whole group's
            // combined guest capacity, not this single row's own count.
            $cartIdToBookingId = [];
            // One stay (same hotel, same dates) can arrive as several cart
            // rows when a party is split across room types. They belong to one
            // party, so the first row's booking anchors the rest - otherwise
            // partyGuestsCount() would only ever see a fraction of the group
            // and a ferry ticket for everyone would be refused.
            $stayAnchors = [];
            $result = ['hotel' => [], 'ferry' => [], 'themepark' => []];

            foreach ($items as $item) {
                if ($item['type'] === 'hotel') {
                    $data = Validator::make($item, [
                        'representativeRoomId' => ['required', 'exists:rooms,id'],
                        'checkIn' => ['required', 'date'],
                        'checkOut' => ['required', 'date', 'after:checkIn'],
                        'guestsCount' => ['required', 'integer', 'min:1'],
                        'quantity' => ['sometimes', 'integer', 'min:1'],
                    ])->validate();

                    $bookings = $this->hotelBookings->create($userId, [
                        'room_id' => $data['representativeRoomId'],
                        'check_in_date' => $data['checkIn'],
                        'check_out_date' => $data['checkOut'],
                        'guests_count' => $data['guestsCount'],
                        'quantity' => $data['quantity'] ?? 1,
                    ]);
                    // Reassigned because settle() returns freshly-read models; the
                    // rest of this branch reads status and ids off $bookings.
                    $bookings = $this->hotelBookings->settle($bookings, $actor);

                    $stayKey = implode('|', [
                        Room::findOrFail($data['representativeRoomId'])->hotel_id,
                        $data['checkIn'],
                        $data['checkOut'],
                    ]);

                    if (isset($stayAnchors[$stayKey])) {
                        foreach ($bookings as $booking) {
                            $booking->update(['group_booking_id' => $stayAnchors[$stayKey]]);
                        }
                    } else {
                        // create() already linked this row's own siblings to
                        // its first booking, so that same row is the anchor.
                        $stayAnchors[$stayKey] = $bookings->first()->id;
                    }

                    $cartIdToBookingId[$item['id']] = $bookings->first()->id;
                    $result['hotel'] = [...$result['hotel'], ...$bookings->all()];
                } elseif ($item['type'] === 'ferry') {
                    $data = Validator::make($item, [
                        'scheduleId' => ['required', 'exists:ferry_schedules,id'],
                        'seatNumbers' => ['required', 'array', 'min:1'],
                        'seatNumbers.*' => ['integer', 'min:1', 'distinct'],
                        'paymentMethod' => ['required', 'in:online,cash'],
                    ])->validate();

                    $bookingId = $item['bookingId'] ?? ($cartIdToBookingId[$item['hotelCartItemId'] ?? null] ?? null);
                    if (! $bookingId) {
                        throw ValidationException::withMessages([
                            "items.{$item['id']}" => 'Its linked hotel booking is missing.',
                        ]);
                    }

                    $tickets = $this->ferryTickets->issue($userId, [
                        'schedule_id' => $data['scheduleId'],
                        'booking_id' => $bookingId,
                        'seat_numbers' => $data['seatNumbers'],
                        'payment_method' => $data['paymentMethod'],
                    ]);
                    $result['ferry'] = [...$result['ferry'], ...$tickets->all()];
                } else {
                    $data = Validator::make($item, [
                        'slotId' => ['required', 'exists:event_slots,id'],
                        'ticketCount' => ['required', 'integer', 'min:1'],
                    ])->validate();

                    $result['themepark'][] = $this->themeParkBookings->book($userId, [
                        'event_slot_id' => $data['slotId'],
                        'ticket_count' => $data['ticketCount'],
                    ]);
                }
            }

            return $result;
        });

        return response()->json($created, 201);
    }
}
