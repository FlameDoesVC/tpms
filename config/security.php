<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Read by AppServiceProvider (which configures Password::defaults(), the rule
    | used by every password-setting endpoint) and injected into the SPA shell so
    | the strength indicator states the same requirement the server enforces.
    | One source of truth: a mismatch here shows the user a rule that isn't real.
    |
    | The policy is deliberately length-first with no composition requirements.
    | Mandatory upper/lower/digit/symbol rules are what make a password field
    | infuriating, and they measurably push people toward predictable shapes like
    | "Password1!" - which a cracker tries first. NIST SP 800-63B advises against
    | them for exactly that reason, and asks for a breach-corpus check instead.
    |
    */

    'password' => [

        // OWASP ASVS 4.0 L2. Long enough to matter, and with no composition
        // rules a short phrase clears it easily.
        'min_length' => (int) env('PASSWORD_MIN_LENGTH', 12),

        // bcrypt silently truncates at 72 bytes, which would make two different
        // long passwords equivalent. Rejecting them is honest; truncating is not.
        'max_length' => 72,

        // Reject passwords that appear in known breach corpora, via the Have I
        // Been Pwned range API. The password never leaves this server: only the
        // first five characters of its SHA-1 hash are sent (k-anonymity), and
        // the check fails open, so an API outage cannot block registration.
        'check_breaches' => (bool) env('PASSWORD_CHECK_BREACHES', true),

    ],

];
