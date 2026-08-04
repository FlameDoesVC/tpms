<?php

namespace App\Policies;

use App\Models\FerryScheduleTemplate;
use App\Models\User;

class FerryScheduleTemplatePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ferry.schedules.manage');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('ferry.schedules.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FerryScheduleTemplate $ferryScheduleTemplate): bool
    {
        return $user->can('ferry.schedules.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FerryScheduleTemplate $ferryScheduleTemplate): bool
    {
        return $user->can('ferry.schedules.manage');
    }
}
