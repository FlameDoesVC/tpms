// Client-side view of the server's password policy.
//
// The limits come from the page's meta tags, which are rendered from
// config/security.php - so the requirement shown to the user is the requirement
// that will actually be enforced. Everything here is advisory: the server
// re-validates, and the breach-corpus check can only happen there.

const meta = (name, fallback) => {
    // Guarded so this module can be imported without a DOM (a test runner, or a
    // build-time import) instead of throwing at load.
    if (typeof document === 'undefined') return fallback;

    const content = document.querySelector(`meta[name="${name}"]`)?.content;
    const parsed = Number.parseInt(content ?? '', 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

export const MIN_LENGTH = meta('password-min-length', 12);
export const MAX_LENGTH = meta('password-max-length', 72);

// The handful of passwords people actually reach for first. Not a security
// control - the server checks the full breach corpus - but catching these as the
// user types is far kinder than a round trip that says "this was in a breach".
const OBVIOUS = [
    'password', 'passw0rd', 'password1', 'password123', 'passwordpassword',
    '123456', '12345678', '123456789', '1234567890', '111111', '000000',
    'qwerty', 'qwertyuiop', 'qwerty123', 'asdfghjkl', 'iloveyou',
    'letmein', 'welcome', 'welcome1', 'admin', 'administrator', 'root',
    'abc123', 'monkey', 'dragon', 'sunshine', 'princess', 'football',
    'baseball', 'superman', 'trustno1', 'changeme', 'secret',
    // This project's own demo credential, which is the one most likely to be
    // typed in by someone who has read the seeder.
    'tpms', 'tpms123', 'maldives', 'island',
];

const CLASSES = [
    /[a-z]/,
    /[A-Z]/,
    /\d/,
    /[^A-Za-z0-9]/,
];

/**
 * Characters that aren't part of a run of the same character, or of a simple
 * ascending/descending sequence. "aaaaaaaaaaaa" and "abcdefghijkl" are long but
 * carry almost no entropy, and a purely length-based score would call them
 * strong.
 */
const effectiveLength = (password) => {
    // Credit for continuing a run stops after a few characters. Awarding a
    // fraction per character indefinitely let 80 repeats of "a" accumulate enough
    // to score as a good password.
    const MAX_RUN_CREDITS = 3;

    let effective = 0;
    let previous = null;
    let step = null;
    let runLength = 0;

    for (const char of password) {
        const code = char.codePointAt(0);
        const delta = previous === null ? null : code - previous;
        const continuesRun = delta !== null && delta === step && Math.abs(delta) <= 1;

        if (continuesRun) {
            runLength += 1;
            // A short run still beats none, so "aaa" scores above "aa".
            effective += runLength <= MAX_RUN_CREDITS ? 0.25 : 0;
        } else {
            runLength = 0;
            effective += 1;
        }

        step = delta;
        previous = code;
    }

    return effective;
};

const looksObvious = (password) => {
    const normalised = password.toLowerCase().replace(/[^a-z0-9]/g, '');

    if (normalised.length === 0) return false;

    return OBVIOUS.some((entry) => normalised === entry || normalised.startsWith(entry));
};

/**
 * Advisory strength assessment.
 *
 * @returns {{
 *   score: number, label: string, tone: string,
 *   meetsMinimum: boolean, tooLong: boolean, obvious: boolean,
 *   hint: string|null, empty: boolean
 * }}
 */
export function evaluatePassword(password) {
    const value = password ?? '';
    const meetsMinimum = value.length >= MIN_LENGTH;
    const tooLong = value.length > MAX_LENGTH;
    const obvious = looksObvious(value);

    if (value.length === 0) {
        return {
            score: 0,
            label: '',
            tone: 'idle',
            meetsMinimum: false,
            tooLong: false,
            obvious: false,
            hint: null,
            empty: true,
        };
    }

    if (!meetsMinimum) {
        return {
            score: 0,
            label: 'Too short',
            tone: 'weak',
            meetsMinimum: false,
            tooLong,
            obvious,
            hint: `${MIN_LENGTH - value.length} more character${MIN_LENGTH - value.length === 1 ? '' : 's'} to go.`,
            empty: false,
        };
    }

    // Over the cap it will be rejected, so it must not read as acceptable.
    if (tooLong) {
        return {
            score: 0,
            label: 'Too long',
            tone: 'weak',
            meetsMinimum: true,
            tooLong: true,
            obvious,
            hint: `Passwords are capped at ${MAX_LENGTH} characters.`,
            empty: false,
        };
    }

    if (obvious) {
        return {
            score: 1,
            label: 'Too common',
            tone: 'weak',
            meetsMinimum: true,
            tooLong,
            obvious: true,
            hint: 'This is one of the first passwords an attacker tries. Pick something unrelated to it.',
            empty: false,
        };
    }

    const effective = effectiveLength(value);
    const variety = CLASSES.filter((pattern) => pattern.test(value)).length;

    // Length carries the score; variety nudges it. A long phrase of plain words
    // beats a short scramble of symbols, which is also what the maths says.
    let score = 1;
    if (effective >= MIN_LENGTH + 2) score += 1;
    if (effective >= 16) score += 1;
    if (effective >= 20 && variety >= 2) score += 1;
    if (variety >= 3 && effective >= 14) score += 1;

    score = Math.min(score, 4);

    const labels = { 1: 'Weak', 2: 'Fair', 3: 'Good', 4: 'Strong' };
    const tones = { 1: 'weak', 2: 'fair', 3: 'good', 4: 'strong' };

    const hint = score <= 2
        ? 'A few unrelated words make a password both stronger and easier to remember.'
        : null;

    return {
        score,
        label: labels[score],
        tone: tones[score],
        meetsMinimum: true,
        tooLong,
        obvious: false,
        hint,
        empty: false,
    };
}
