<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Database\Factories\MaintenanceTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceTask extends Model
{
    /** @use HasFactory<MaintenanceTaskFactory> */
    use HasFactory;

    protected $fillable = ['unit_id', 'plan_id', 'due_at', 'done_at', 'cost', 'notes', 'status'];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'due_at' => 'datetime',
            'done_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'plan_id');
    }

    #[Scope]
    protected function open(Builder $query): Builder
    {
        return $query->where('status', MaintenanceStatus::Open);
    }
}
