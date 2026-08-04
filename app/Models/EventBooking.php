<?php

namespace App\Models;

use App\Models\Concerns\HasReferenceCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBooking extends Model
{
    use HasFactory;
    use HasReferenceCode;

    protected $fillable = [
        'user_id',
        'visitor_name',
        'event_slot_id',
        'ticket_count',
        'status',
        'reference_code',
        'validated_by',
        'validated_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Matches Booking (VFN-B) and FerryTicket (VFN-T) so a visitor has something
     * to present at the gate, and so a scan can be routed to the right lookup.
     */
    public static function referenceCodePrefix(): string
    {
        return 'VFN-E';
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
