<?php

namespace App\Models;

use Database\Factories\LateFeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LateFee extends Model
{
    /** @use HasFactory<LateFeeFactory> */
    use HasFactory;

    protected $fillable = ['rental_id', 'days_late', 'amount', 'calculated_at'];

    protected function casts(): array
    {
        return [
            'days_late' => 'integer',
            'amount' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }
}
