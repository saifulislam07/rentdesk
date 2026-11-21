<?php

namespace App\Models;

use Database\Factories\KitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kit extends Model
{
    /** @use HasFactory<KitFactory> */
    use HasFactory;

    protected $fillable = ['name', 'price'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(KitItem::class);
    }
}
