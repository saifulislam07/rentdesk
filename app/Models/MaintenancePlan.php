<?php

namespace App\Models;

use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'every_days', 'every_rentals'];

    protected function casts(): array
    {
        return [
            'every_days' => 'integer',
            'every_rentals' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class, 'plan_id');
    }
}
