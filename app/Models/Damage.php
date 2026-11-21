<?php

namespace App\Models;

use App\Enums\DamageStatus;
use Database\Factories\DamageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Damage extends Model
{
    /** @use HasFactory<DamageFactory> */
    use HasFactory;

    protected $fillable = ['rental_id', 'unit_id', 'inspection_id', 'description', 'cost', 'charged_amount', 'status'];

    protected function casts(): array
    {
        return [
            'status' => DamageStatus::class,
            'cost' => 'decimal:2',
            'charged_amount' => 'decimal:2',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
