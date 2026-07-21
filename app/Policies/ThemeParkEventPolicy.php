<?php

namespace App\Policies;

use App\Models\ThemeParkEvent;
use App\Models\User;

class ThemeParkEventPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('themepark_staff');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ThemeParkEvent $themeParkEvent): bool
    {
        return $user->hasRole('themepark_staff');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ThemeParkEvent $themeParkEvent): bool
    {
        return $user->hasRole('themepark_staff');
    }
}
