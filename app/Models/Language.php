<?php

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    /** The language the application's strings are written in; it needs no translation file. */
    public const BASE = 'en';

    protected $fillable = ['code', 'name', 'dir', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function isBase(): bool
    {
        return $this->code === self::BASE;
    }

    public function isDefault(): bool
    {
        return $this->code === config('app.locale');
    }

    /**
     * The language can be removed or switched off only if it is not the base or the current default.
     */
    public function isProtected(): bool
    {
        return $this->isBase() || $this->isDefault();
    }
}
