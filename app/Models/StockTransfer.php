<?php

namespace App\Models;

use App\Enums\TransferStatus;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    protected $fillable = ['unit_id', 'from_location_id', 'to_location_id', 'status', 'sent_at', 'received_at'];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
