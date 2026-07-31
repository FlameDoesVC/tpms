<?php

namespace App\Policies;

use App\Models\EventSlotTemplate;
use App\Models\User;

class EventSlotTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['themepark_staff', 'admin']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['themepark_staff', 'admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EventSlotTemplate $eventSlotTemplate): bool
    {
        return $user->hasAnyRole(['themepark_staff', 'admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EventSlotTemplate $eventSlotTemplate): bool
    {
        return $user->hasAnyRole(['themepark_staff', 'admin']);
    }
}
