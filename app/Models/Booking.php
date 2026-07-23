<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'group_booking_id',
        'check_in_date',
        'check_out_date',
        'total_price',
        'status',
        'guests_count',
    ];

    protected $appends = ['reference_code'];

    protected function referenceCode(): Attribute
    {
        return Attribute::get(fn () => sprintf('LSJ-B%04d', $this->id));
    }

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'total_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Every room id from the same purchase (itself, plus any sibling rooms
     * sharing its group_booking_id anchor) - a multi-room booking splits
     * guests and ferry tickets across several rows that all belong to one
     * party.
     */
    public function partyBookingIds(): array
    {
        $anchorId = $this->group_booking_id ?? $this->id;

        return static::query()
            ->where(fn ($query) => $query->where('id', $anchorId)->orWhere('group_booking_id', $anchorId))
            ->pluck('id')
            ->all();
    }

    /**
     * Total guests across every room in the party - a single row's own
     * guests_count understates what the whole party can actually fit.
     */
    public function partyGuestsCount(): int
    {
        return static::query()->whereIn('id', $this->partyBookingIds())->sum('guests_count');
    }
}
