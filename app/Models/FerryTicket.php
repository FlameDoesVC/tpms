<?php

namespace App\Models;

use App\Models\Concerns\HasReferenceCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FerryTicket extends Model
{
    use HasFactory;
    use HasReferenceCode;

    protected $fillable = [
        'user_id',
        'schedule_id',
        'booking_id',
        'seat_number',
        'status',
        'price',
        'payment_method',
        'reference_code',
        'validated_by',
        'validated_at',
        'cancelled_by',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'validated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function referenceCodePrefix(): string
    {
        return 'VFN-T';
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
