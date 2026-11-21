<?php

namespace App\Models;

use App\Enums\InspectionType;
use App\Enums\UnitCondition;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'unit_id',
        'type',
        'condition',
        'meter_reading',
        'checklist',
        'notes',
        'signature_path',
        'inspected_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => InspectionType::class,
            'condition' => UnitCondition::class,
            'meter_reading' => 'integer',
            'checklist' => 'array',
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

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionPhoto::class);
    }
}
