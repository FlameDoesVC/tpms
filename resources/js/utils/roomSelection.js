// Decides whether a room choice is bookable, and what to tell the visitor.
//
// The rule this replaces required a single room type to seat the entire party
// on its own, which made an ordinary mixed booking ("two singles and a double
// for three") impossible to express. Capacity now accumulates across every
// room already chosen for the same stay, so room types compose.
//
// Kept as pure functions so the matrix of party sizes, room sizes and
// part-finished selections can be tested directly.

/** Rooms already in the itinerary for one stay: same hotel, same dates. */
export function roomsForStay(cartItems, { hotelId, checkIn, checkOut }) {
    return cartItems.filter(
        (item) =>
            item.type === 'hotel' &&
            item.hotelId === hotelId &&
            item.checkIn === checkIn &&
            item.checkOut === checkOut
    );
}

export function bookedCapacity(rows) {
    return rows.reduce((sum, item) => sum + item.quantity * item.maxGuests, 0);
}

export function bookedRooms(rows) {
    return rows.reduce((sum, item) => sum + item.quantity, 0);
}

/** How many people still have nowhere to sleep. */
export function guestsStillNeeded(guests, capacityAlreadyBooked) {
    return Math.max(0, guests - capacityAlreadyBooked);
}

/**
 * Suggested quantity for a room type: enough to cover whoever is left over,
 * not the whole party. Once two of three guests have a bed, the next room type
 * only needs to sleep one.
 */
export function suggestedQuantity({ guests, capacityBooked, maxGuests, availableCount }) {
    const needed = guestsStillNeeded(guests, capacityBooked);
    if (needed === 0) return 1;
    return Math.min(availableCount, Math.max(1, Math.ceil(needed / maxGuests)));
}

/**
 * Verdict for one room-type row, given what's already chosen for the stay.
 *
 * Only what the server itself refuses is refused here:
 *   - a quantity the hotel cannot supply
 *   - a quantity that isn't a number of rooms at all
 *
 * Nothing else is blocked. The server's only capacity rule is that the rooms
 * must fit the guests (HotelBookingService), never that guests must fill the
 * rooms - so booking more rooms than people is a choice, not an error, and
 * gets a note at most. One guest in a suite, a party split across room types,
 * a second room for a caregiver, kit or a bit of privacy are all legitimate.
 */
export function evaluateRoomChoice({
    guests,
    quantity,
    maxGuests,
    availableCount,
    capacityBooked = 0,
    roomsBooked = 0,
}) {
    const projectedRooms = roomsBooked + quantity;
    const projectedCapacity = capacityBooked + quantity * maxGuests;
    const shortfall = Math.max(0, guests - projectedCapacity);

    // Even every remaining room of this type can't finish the job alone, so
    // the answer is another room type rather than a bigger number here.
    const cannotFinishAlone = capacityBooked + availableCount * maxGuests < guests;

    // The stay is already sorted before this row is considered at all.
    const alreadyCovered = capacityBooked >= guests;

    const invalidQuantity = !Number.isFinite(quantity) || quantity < 1;
    const exceedsStock = quantity > availableCount;

    // More rooms on THIS row than the people it could possibly be for. Judged
    // per row, against who is still unplaced, rather than against the stay's
    // running total - that distinction is the whole fix for the bug this
    // replaced. A stay-wide test fires on every row at once the moment the
    // party is covered (for a solo traveller, immediately after the first
    // room), so it stopped meaning anything and read as "you did it wrong" on
    // rows the visitor had not touched. Keyed to the quantity actually typed,
    // it can only ever appear on the row being edited.
    //
    // A solo traveller is this same case, not a special one. Treating them
    // separately - and as a hard block - is what made an ordinary second room
    // impossible to add at all.
    const overRoomed = quantity > Math.max(1, guestsStillNeeded(guests, capacityBooked));

    let status = 'ok';
    if (invalidQuantity) status = 'invalid-quantity';
    else if (exceedsStock) status = 'exceeds-stock';
    else if (overRoomed) status = 'over-roomed';
    else if (shortfall > 0) status = 'shortfall';

    const blocked = invalidQuantity || exceedsStock;

    return {
        status,
        canBook: !blocked,
        // 'danger' disables the action; 'warning' and 'info' never do.
        severity: blocked ? 'danger' : status === 'over-roomed' ? 'warning' : status === 'shortfall' ? 'info' : null,
        projectedRooms,
        projectedCapacity,
        shortfall,
        cannotFinishAlone,
    };
}

/**
 * Guests to record against this row. Each row carries only the people it
 * actually holds, so the server's per-booking capacity check stays true when a
 * stay is split across room types. Floored at 1: a room added on top of an
 * already-covered party still has someone's name against it.
 */
export function guestsForRow({ guests, capacityBooked, quantity, maxGuests }) {
    const capacity = quantity * maxGuests;
    return Math.max(1, Math.min(guestsStillNeeded(guests, capacityBooked), capacity));
}
