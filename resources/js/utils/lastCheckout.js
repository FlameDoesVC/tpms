// The checkout result is the only place a visitor ever sees their new
// reference codes, and the cart is cleared the moment it arrives. Holding it
// only in component state meant a refresh - or a back/forward - landed on an
// empty cart with no record of what was just bought.
//
// sessionStorage rather than a route with ids: the result spans hotel, ferry
// and park rows with no single endpoint to re-fetch them from, and a
// guest-checkout session may not survive a reload well enough to try.
const KEY = 'tpms.lastCheckout.v1';
const MAX_AGE_MS = 24 * 60 * 60 * 1000;

export function saveLastCheckout(result) {
    try {
        sessionStorage.setItem(KEY, JSON.stringify({ at: Date.now(), result }));
    } catch {
        // Private mode or a full quota - the in-memory copy still works for
        // this pageview, so a failure here isn't worth surfacing.
    }
}

export function loadLastCheckout() {
    try {
        const raw = sessionStorage.getItem(KEY);
        if (!raw) return null;

        const { at, result } = JSON.parse(raw);
        if (!at || Date.now() - at > MAX_AGE_MS) {
            clearLastCheckout();
            return null;
        }
        return result ?? null;
    } catch {
        return null;
    }
}

export function clearLastCheckout() {
    try {
        sessionStorage.removeItem(KEY);
    } catch {
        // Nothing to recover from.
    }
}
