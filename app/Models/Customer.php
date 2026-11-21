<?php

namespace App\Models;

use App\Enums\CustomerType;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    protected $fillable = ['type', 'name', 'email', 'phone', 'address', 'tax_no', 'is_blacklisted', 'notes'];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'is_blacklisted' => 'boolean',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    #[Scope]
    protected function notBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', false);
    }

    /**
     * Match a search term against the name, email and phone.
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $query, string $term): void {
            $like = '%'.addcslashes($term, '%_\\').'%';

            $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like));
        });
    }
}
