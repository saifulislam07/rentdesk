<?php

namespace App\Models;

use App\Services\Settings;
use Illuminate\Database\Eloquent\Model;

/**
 * One administrator-managed setting, stored as text and cast by {@see Settings}.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];
}
