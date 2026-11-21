<?php

namespace App\Models;

use Database\Factories\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    /** @use HasFactory<InvoiceItemFactory> */
    use HasFactory;

    protected $fillable = ['invoice_id', 'description', 'qty', 'price'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineTotal(): string
    {
        return number_format($this->qty * (float) $this->price, 2, '.', '');
    }
}
