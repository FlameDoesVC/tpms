<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Uploaded images, re-encoded before they are ever served.
 *
 * Uploads are validated (`image` + a jpeg/png/webp/gif allowlist, no SVG) and
 * stored under a generated path, but the bytes were previously kept verbatim and
 * served from the application's own origin. Re-encoding through GD discards
 * everything that is not pixel data: EXIF (which routinely carries GPS
 * coordinates from a phone camera) and any payload appended to a file crafted to
 * satisfy getimagesize() while also parsing as markup.
 *
 * The conversion is deliberately NOT queued: a queued conversion leaves the
 * unsanitised original as the only thing available to serve until a worker picks
 * it up, which on a deployment without a running worker is forever.
 */
trait HasSanitisedImage
{
    /** Longest edge, in pixels. Larger uploads are scaled down. */
    private const MAX_EDGE = 1600;

    /**
     * Register the sanitising conversion.
     *
     * Called from each model's own registerMediaConversions(), rather than
     * declared as one here: InteractsWithMedia already declares that method, and
     * two traits providing the same method is a fatal collision.
     */
    protected function registerSanitisedImageConversion(): void
    {
        $this->addMediaConversion('display')
            ->nonQueued()
            ->width(self::MAX_EDGE)
            ->height(self::MAX_EDGE);
    }

    /**
     * Prefers the sanitised rendition, falling back to the original so media
     * uploaded before this conversion existed still displays.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getFirstMediaUrl('image', 'display')
                ?: ($this->getFirstMediaUrl('image') ?: null)
        );
    }
}
