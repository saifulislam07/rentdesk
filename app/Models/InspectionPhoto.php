<?php

namespace App\Models;

use Database\Factories\InspectionPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionPhoto extends Model
{
    /** @use HasFactory<InspectionPhotoFactory> */
    use HasFactory;

    protected $fillable = ['inspection_id', 'path'];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
