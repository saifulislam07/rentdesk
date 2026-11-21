<?php

namespace App\Models;

use App\Enums\UnitCondition;
use App\Enums\UnitStatus;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'location_id',
        'serial_no',
        'condition',
        'status',
        'quantity',
        'purchased_at',
        'purchase_cost',
        'meter_reading',
    ];

    protected $attributes = [
        'condition' => 'new',
        'status' => 'available',
        'quantity' => 1,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $unit): void {
            $unit->qr_code ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'condition' => UnitCondition::class,
            'status' => UnitStatus::class,
            'quantity' => 'integer',
            'purchased_at' => 'date',
            'purchase_cost' => 'decimal:2',
            'meter_reading' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function maintenanceTasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class);
    }

    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    /**
     * Units whose status allows them to be booked.
     */
    #[Scope]
    protected function rentable(Builder $query): Builder
    {
        return $query->whereNotIn('status', [UnitStatus::Maintenance, UnitStatus::Retired, UnitStatus::InTransit]);
    }
}
