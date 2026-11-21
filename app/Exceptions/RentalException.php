<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A rental operation is not allowed in the rental's current state. The message is safe to show to users.
 */
class RentalException extends RuntimeException {}
