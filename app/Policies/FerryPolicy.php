<?php

namespace App\Policies;

use App\Models\Ferry;
use App\Models\User;

class FerryPolicy
{
    /**
     * Same audience as the schedules these boats carry - whoever runs the
     * sailings owns the fleet they run on.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['ferry_operator', 'admin']);
    }

    public function update(User $user, Ferry $ferry): bool
    {
        return $user->hasAnyRole(['ferry_operator', 'admin']);
    }

    public function delete(User $user, Ferry $ferry): bool
    {
        return $user->hasAnyRole(['ferry_operator', 'admin']);
    }
}
