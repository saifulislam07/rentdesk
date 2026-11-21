<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Services\Money;
use Database\Factories\RentalFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rental extends Model
{
    /** @use HasFactory<RentalFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'customer_id',
        'location_id',
        'status',
        'start_at',
        'end_at',
        'returned_at',
        'overdue_reminded_at',
        'subtotal',
        'tax',
        'discount',
        'total',
        'deposit_required',
        'currency',
        'notes',
    ];

    protected $attributes = [
        'status' => 'quote',
    ];

    protected function casts(): array
    {
        return [
            'status' => RentalStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'returned_at' => 'datetime',
            'overdue_reminded_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'deposit_required' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(Damage::class);
    }

    public function lateFee(): HasOne
    {
        return $this->hasOne(LateFee::class);
    }

    /**
     * Out and past its end time.
     */
    public function isOverdue(): bool
    {
        return $this->status === RentalStatus::Out && $this->end_at->isPast();
    }

    public function deposit(): HasOne
    {
        return $this->hasOne(Deposit::class);
    }

    /**
     * Total charged to the customer for damage, as a decimal string.
     */
    public function chargedDamageTotal(): string
    {
        return Money::format($this->damages->sum(fn (Damage $damage): int => Money::toCents($damage->charged_amount)));
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    /**
     * Rentals whose items keep their units unavailable.
     */
    #[Scope]
    protected function blocking(Builder $query): Builder
    {
        return $query->whereIn('status', RentalStatus::blocking());
    }
}
