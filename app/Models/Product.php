<?php

namespace App\Models;

use App\Enums\ProductTracking;
use App\Enums\RatePeriod;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUniqueSlug;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'tracking',
        'description',
        'replacement_value',
        'deposit_amount',
        'buffer_minutes',
    ];

    protected function casts(): array
    {
        return [
            'tracking' => ProductTracking::class,
            'replacement_value' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'buffer_minutes' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function maintenancePlan(): HasOne
    {
        return $this->hasOne(MaintenancePlan::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
    }

    /**
     * Replace the product's rates with the submitted ones. A period without a price is removed.
     *
     * @param  array<string, array{price?: string|null, min_qty_periods?: string|null, weekend_price?: string|null}>  $rates
     */
    public function syncRates(array $rates): void
    {
        foreach (RatePeriod::cases() as $period) {
            $rate = $rates[$period->value] ?? [];

            if (blank($rate['price'] ?? null)) {
                $this->rates()->where('period', $period)->delete();

                continue;
            }

            $this->rates()->updateOrCreate(
                ['period' => $period],
                [
                    'price' => $rate['price'],
                    'min_qty_periods' => ($rate['min_qty_periods'] ?? null) ?: 1,
                    'weekend_price' => ($rate['weekend_price'] ?? null) ?: null,
                ]
            );
        }
    }
}
