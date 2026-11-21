<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\PaymentMethod;
use Database\Factories\DepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    /** @use HasFactory<DepositFactory> */
    use HasFactory;

    protected $fillable = ['rental_id', 'amount', 'method', 'status', 'refunded_amount', 'settled_at'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => DepositStatus::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function isHeld(): bool
    {
        return $this->status === DepositStatus::Held;
    }
}
