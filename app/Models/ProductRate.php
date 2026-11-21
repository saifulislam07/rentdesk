<?php

namespace App\Models;

use App\Enums\RatePeriod;
use Database\Factories\ProductRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRate extends Model
{
    /** @use HasFactory<ProductRateFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'period', 'price', 'min_qty_periods', 'weekend_price'];

    protected function casts(): array
    {
        return [
            'period' => RatePeriod::class,
            'price' => 'decimal:2',
            'weekend_price' => 'decimal:2',
            'min_qty_periods' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
