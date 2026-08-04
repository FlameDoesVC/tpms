<?php

namespace App\Policies;

use App\Models\FerrySchedule;
use App\Models\User;

class FerrySchedulePolicy
{
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
    public function update(User $user, FerrySchedule $ferrySchedule): bool
    {
        return $user->can('ferry.schedules.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FerrySchedule $ferrySchedule): bool
    {
        return $user->can('ferry.schedules.manage');
    }
}
