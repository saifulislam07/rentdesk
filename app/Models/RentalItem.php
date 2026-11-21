<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RentalItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalItem extends Model
{
    /** @use HasFactory<RentalItemFactory> */
    use HasFactory;

    protected $fillable = ['rental_id', 'product_id', 'unit_id', 'kit_id', 'qty', 'start_at', 'end_at', 'rate', 'amount'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Items of blocking rentals that overlap the period once the buffer is added on both sides.
     */
    #[Scope]
    protected function overlapping(Builder $query, CarbonInterface $start, CarbonInterface $end, int $bufferMinutes = 0): Builder
    {
        return $query
            ->where('start_at', '<', $end->copy()->addMinutes($bufferMinutes))
            ->where('end_at', '>', $start->copy()->subMinutes($bufferMinutes))
            ->whereHas('rental', fn (Builder $rental) => $rental->blocking());
    }

    #[Scope]
    protected function excludingRental(Builder $query, ?int $rentalId): Builder
    {
        return $query->when($rentalId, fn (Builder $items) => $items->where('rental_id', '!=', $rentalId));
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
