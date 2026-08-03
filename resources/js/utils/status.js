// One status vocabulary for every visitor-facing badge. The three "my
// bookings" pages each grew their own map, which is how `used` ended up
// rendering as info on one page and neutral on another.
const VARIANTS = {
    // Money still owed, or awaiting an action from the visitor.
    pending: 'warning',
    // Locked in.
    confirmed: 'success',
    issued: 'success',
    scheduled: 'success',
    // Spent, but not a failure - distinct from cancelled on purpose.
    used: 'info',
    // Inert.
    cancelled: 'neutral',
    departed: 'neutral',
};

export function statusVariant(status) {
    return VARIANTS[status] ?? 'neutral';
}

/** Statuses a visitor can no longer act on. */
export function isInactiveStatus(status) {
    return status === 'cancelled' || status === 'departed';
}
