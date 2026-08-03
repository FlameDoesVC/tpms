<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'visitor_name',
        'event_slot_id',
        'ticket_count',
        'status',
    ];

    protected $appends = ['reference_code'];

    /**
     * Matches Booking (VFN-B) and FerryTicket (VFN-T) so a visitor has
     * something to present at the gate. The staff scanner reads the trailing
     * digits as the id, so the letter only has to be distinct to humans.
     */
    protected function referenceCode(): Attribute
    {
        return Attribute::get(fn () => sprintf('VFN-E%04d', $this->id));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(EventSlot::class, 'event_slot_id');
    }
}
