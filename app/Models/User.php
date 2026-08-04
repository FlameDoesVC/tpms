<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

// `is_guest` is deliberately NOT fillable: it decides whether the guest-claim and
// guest-login endpoints will act on an account, so a mass-assignable copy of it
// is a privilege boundary one careless ->create($request->all()) away from being
// writable. It is set explicitly in createGuest() and cleared in
// GuestController::claim().
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_guest' => 'boolean',
        ];
    }

    /**
     * Create a placeholder account for guest checkout. Populated later via
     * the guest-claim endpoint once the visitor supplies real details.
     */
    public static function createGuest(): self
    {
        $guest = self::make([
            'name' => 'Guest',
            'email' => 'guest-'.Str::uuid().'@guest.tpms.local',
            'password' => Hash::make(Str::random(40)),
        ]);

        // Set outside the fillable set on purpose - see the note on the class.
        $guest->is_guest = true;
        $guest->save();

        $guest->assignRole('visitor');

        return $guest;
    }

    /**
     * Guest placeholders own an unreachable @guest.tpms.local address, so there is
     * nothing to verify and nothing to send. A verification mail is only sent once
     * the visitor supplies a real address via the guest-claim endpoint.
     */
    public function hasVerifiedEmail(): bool
    {
        return $this->is_guest || ! is_null($this->email_verified_at);
    }
}
