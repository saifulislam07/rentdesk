<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\CustomerDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDocument extends Model
{
    /** @use HasFactory<CustomerDocumentFactory> */
    use HasFactory;

    protected $fillable = ['customer_id', 'type', 'file_path', 'original_name', 'verified_at'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
