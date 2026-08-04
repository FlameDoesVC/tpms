<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One definition of "who may see a deactivated record".
 *
 * `is_active` is how this application takes something off sale, and it used to be
 * honoured on some public endpoints and ignored on adjacent ones returning the
 * same data: the ferry list deliberately returned 403 for the unfiltered view
 * while every retired boat walked out of the schedules endpoint. Hotels were
 * filtered on the homepage and unfiltered on the browse page. Unannounced park
 * events were readable by id and through the staff calendar.
 *
 * Staff need the unfiltered list to re-activate anything they have hidden, so
 * this is a scope with an opt-in rather than a blanket filter.
 */
trait HasVisibilityScope
{
    /** Roles allowed to opt into seeing hidden records. */
    private const MANAGEMENT_ROLES = [
        'hotel_manager',
        'themepark_staff',
        'ferry_operator',
        'admin',
    ];

    public function scopeVisibleTo(Builder $query, ?User $user, bool $wantsAll = false): Builder
    {
        return $wantsAll && self::isManagement($user)
            ? $query
            : $query->where('is_active', true);
    }

    /**
     * Whether this specific record may be shown to the given user. Used by the
     * single-record endpoints, which have no query to scope.
     */
    public function visibleTo(?User $user): bool
    {
        return (bool) $this->is_active || self::isManagement($user);
    }

    private static function isManagement(?User $user): bool
    {
        return $user?->hasAnyRole(self::MANAGEMENT_ROLES) ?? false;
    }
}
