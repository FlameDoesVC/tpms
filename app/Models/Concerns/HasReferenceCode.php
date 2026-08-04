<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * An unguessable, stored reference code for anything presented at a gate.
 *
 * These codes used to be derived from the primary key, which made every ticket
 * and booking in the system enumerable from any single one of them - and the code
 * is the whole credential, since the scanner accepts whatever it is shown. The
 * prefix letter is retained so a scan can be routed to the right lookup without
 * a database round trip; it carries no secrecy.
 *
 * Implementers must define referenceCodePrefix().
 */
trait HasReferenceCode
{
    /** Random component length. 12 base-36 characters is ~62 bits. */
    private const REFERENCE_RANDOM_LENGTH = 12;

    public static function bootHasReferenceCode(): void
    {
        static::creating(function (self $model) {
            $model->reference_code ??= static::generateReferenceCode();
        });
    }

    public static function generateReferenceCode(): string
    {
        do {
            $code = static::referenceCodePrefix().Str::upper(Str::random(self::REFERENCE_RANDOM_LENGTH));
        } while (static::where('reference_code', $code)->exists());

        return $code;
    }

    /** Resolve a scanned code, or fail. */
    public static function findByReferenceCode(string $code): static
    {
        return static::where('reference_code', Str::upper(trim($code)))->firstOrFail();
    }
}
