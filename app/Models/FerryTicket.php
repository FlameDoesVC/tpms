<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FerryTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'booking_id',
        'seat_number',
        'status',
        'price',
        'payment_method',
    ];

    protected $appends = ['reference_code'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    protected function referenceCode(): Attribute
    {
        return Attribute::get(fn () => sprintf('VFN-T%04d', $this->id));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(FerrySchedule::class, 'schedule_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
