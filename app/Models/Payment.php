<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'payable_type',
        'payable_id',
        'amount',
        'method',
        'status',
        'reference',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** The booking, ferry ticket or event booking this settled. */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Who the payment is for. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Who took it - the payer online, or the staff member for cash. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
