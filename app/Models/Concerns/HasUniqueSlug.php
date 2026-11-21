<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Generates a unique slug from the model's name when none is provided.
 */
trait HasUniqueSlug
{
    protected static function bootHasUniqueSlug(): void
    {
        static::saving(function (self $model): void {
            if (blank($model->slug)) {
                $model->slug = $model->makeUniqueSlug($model->name);
            }
        });
    }

    protected function makeUniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
