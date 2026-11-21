<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use Database\Factories\BookingRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRequest extends Model
{
    /** @use HasFactory<BookingRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'location_id',
        'product_id',
        'qty',
        'start_at',
        'end_at',
        'message',
        'status',
        'rental_id',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingRequestStatus::class,
            'qty' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function isNew(): bool
    {
        return $this->status === BookingRequestStatus::New;
    }
}
