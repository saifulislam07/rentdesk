<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A maintenance or stock-transfer operation is not allowed right now. The message is safe to show to users.
 */
class InventoryException extends RuntimeException {}
