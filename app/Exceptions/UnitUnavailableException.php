<?php

namespace App\Exceptions;

use App\Models\Unit;
use RuntimeException;

class UnitUnavailableException extends RuntimeException
{
    public static function forUnit(Unit $unit): self
    {
        return new self(__('Unit :serial is not available for the selected period.', ['serial' => $unit->serial_no]));
    }
}
