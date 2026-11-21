<?php

namespace App\Models;

use Database\Factories\KitItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitItem extends Model
{
    /** @use HasFactory<KitItemFactory> */
    use HasFactory;

    protected $fillable = ['kit_id', 'product_id', 'qty'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
