<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FerrySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'ferry_id',
        'departure_date',
        'departure_time',
        'arrival_time',
        'available_seats',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
        ];
    }

    public function ferry(): BelongsTo
    {
        return $this->belongsTo(Ferry::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(FerryTicket::class, 'schedule_id');
    }
}
